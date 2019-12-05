<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\File;

//use Balloon\Acl;
//luse Balloon\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Collection\Exception;
use Balloon\Filesystem;
use Balloon\Resource\AttributeResolver;
use Generator;
use League\Event\Emitter;
use MimeType\MimeType;
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\Regex;
use function MongoDB\BSON\toPHP;
use MongoDB\BSON\UTCDateTime;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use MongoDB\Database;
use Balloon\Resource\Factory as ResourceFactory;
use Balloon\Storage\Factory as StorageFactory;
use Balloon\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Balloon\Node\Acl;
use Balloon\User;
use Balloon\User\UserInterface;
use Balloon\Node\NodeInterface;
use Balloon\Collection;
use Normalizer;;
use Balloon\Collection\Factory as CollectionFactory;
use Balloon\Collection\CollectionInterface;
use Balloon\File;

class Factory// extends AbstractNode implements CollectionInterface, IQuota
{
    public const COLLECTION_NAME = 'nodes';

    /**
     * Temporary file patterns.
     *
     * @param array
     **/
    protected $temp_files = [
        '/^\._(.*)$/',     // OS/X resource forks
        '/^.DS_Store$/',   // OS/X custom folder settings
        '/^desktop.ini$/', // Windows custom folder settings
        '/^Thumbs.db$/',   // Windows thumbnail cache
        '/^.(.*).swpx$/',  // ViM temporary files
        '/^.(.*).swx$/',   // ViM temporary files
        '/^.(.*).swp$/',   // ViM temporary files
        '/^\.dat(.*)$/',   // Smultron seems to create these
        '/^~lock.(.*)#$/', // Windows 7 lockfiles
    ];

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Emitter.
     *
     * @var Emitter
     */
    protected $emitter;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Storage cache.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Initialize.
     */
    public function __construct(Database $db, Emitter $emitter, ResourceFactory $resource_factory, LoggerInterface $logger, Acl $acl, CollectionFactory $collection_factory)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->emitter = $emitter;
        $this->acl = $acl;
        $this->resource_factory = $resource_factory;
        $this->collection_factory = $collection_factory;
    }

    /**
     * Copy node with children.
     */
    public function copyTo(UserInterface $user, NodeInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = NodeInterface::DELETED_EXCLUDE): NodeInterface
    {
        $this->emitter->emit('file.factory.preCopy', ...func_get_args());

        if (NodeInterface::CONFLICT_RENAME === $conflict && $parent->childExists($this->name)) {
            $name = $this->getDuplicateName();
        } else {
            $name = $this->name;
        }

        if (NodeInterface::CONFLICT_MERGE === $conflict && $parent->childExists($this->name)) {
            $result = $parent->getChild($this->name);

            if ($result instanceof Collection) {
                $result = $this->copyToCollection($result, $name);
            } else {
                $stream = $this->get();
                if ($stream !== null) {
                    $result->put($stream);
                }
            }
        } else {
            $result = $this->copyToCollection($parent, $name);
        }

        $this->emitter->emit('collection.factory.postCopy', ...array_merge([$result],func_get_args()));
        return $result;
    }


    /**
     * Move node.
     */
    public function moveTo(UserInterface $user, FileInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION): NodeInterface
    {
        if ($node->getParent()->getId() == $parent->getId()) {
            throw new Exception\Conflict(
                'source node '.$node->getName().' is already in the requested parent folder',
                Exception\Conflict::ALREADY_THERE
            );
        }
        if ($node->isSubNode($parent)) {
            throw new Exception\Conflict(
                'node called '.$node->getName().' can not be moved into itself',
                Exception\Conflict::CANT_BE_CHILD_OF_ITSELF
            );
        }
        if (!$this->acl->isAllowed($node, 'w', $user)) {
            throw new ForbiddenException(
                'not allowed to move node '.$node->getName(),
                ForbiddenException::NOT_ALLOWED_TO_MOVE
            );
        }

        $new_name = $this->collection_factory->validateInsert($node, $node->getName(), $conflict, get_class($node));

        if (NodeInterface::CONFLICT_RENAME === $conflict && $new_name !== $this->name) {
            $this->setName($new_name);
            $this->raw_attributes['name'] = $this->name;
        }

        if (($parent->isSpecial() && $this->shared != $parent->getShareId())
          || (!$parent->isSpecial() && $this->isShareMember())
          || ($parent->getMount() != $this->getParent()->getMount())) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete();

            return $new;
        }

        if ($parent->childExists($this->name) && NodeInterface::CONFLICT_MERGE === $conflict) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete(true);

            return $new;
        }

        $this->storage = $this->_parent->getStorage()->move($this, $parent);
        $this->parent = $parent->getRealId();
        $this->owner = $this->_user->getId();

        $this->save(['parent', 'shared', 'owner', 'storage']);

        return $this;
    }

    /**
     * Get used qota.
     */
    public function getQuotaUsage(UserInterface $user): int
    {
        $result = $this->db->{self::COLLECTION_NAME}->aggregate([
            [
                '$match' => [
                    'owner' => $user->getId(),
                    'kind' => 'File',
                    'deleted' => null,
                    'storage_reference' => null,
                ],
            ],
            [
                '$group' => [
                    '_id' => null,
                    'sum' => ['$sum' => '$size'],
                ],
            ],
        ]);

        $result = iterator_to_array($result);
        $sum = 0;
        if (isset($result[0]['sum'])) {
            $sum = $result[0]['sum'];
        }

        return $sum;
    }

    /**
     * Get Share name.
     */
    //TODO:WRONG
    public function getShareName(): string
    {
        if ($this->isShare()) {
            return $this->share_name;
        }

        return $this->fs->findRawNode($this->getShareId())['share_name'];
    }


    /**
     * Check if file is temporary.
     */
    public function isTemporaryFile(FileInterface $file): bool
    {
        foreach ($this->temp_files as $pattern) {
            if (preg_match($pattern, $file->getName())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     */
    public function deleteOne(UserInterface $user, FileInterface $file, bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->acl->isAllowed($file, 'w', $user)) {
            throw new AclException\Forbidden(
                'not allowed to delete node '.$this->name,
                AclException\Forbidden::NOT_ALLOWED_TO_DELETE
            );
        }

        $this->emitter->emit('file.factory.preDelete', ...func_get_args());

        if (true === $force || $this->isTemporaryFile($file)) {
            $result = $this->_forceDelete($user, $file);
            $this->emitter->emit('file.factory.postDelete', func_get_args());
            return $result;
        }

        $ts = new UTCDateTime();
        $storage = $file->getParent()->getStorage()->deleteFile($file);

        $result = $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $file, [
            'deleted' => $ts,
            'storage' => $storage,
        ]);

        /*$result = $this->save([
            'version',
            'storage',
            'deleted',
            'history',
        ], [], $recursion, $recursion_first);*/

        $this->emitter->emit('file.factory.postDelete', ...func_get_args());
        return $result;
    }

    /**
     * Prepare query.
     */
    public function prepareQuery(UserInterface $user, ?array $query = null): array
    {
        $filter = [
            'kind' => 'File',
            'owner' => $user->getId(),
        ];

        if (!empty($query)) {
            $filter = [
                '$and' => [$filter, $query],
            ];
        }

        return $filter;
    }


    /**
     * Get all.
     */
    public function getAll(UserInterface $user, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $filter = $this->prepareQuery($user, $query);
        $that = $this;

        return $this->resource_factory->getAllFrom($this->db->{self::COLLECTION_NAME}, $filter, $offset, $limit, $sort, function (array $resource) use ($user, $that) {
            return $that->build($resource, $user);
        });
    }

    /**
     * Get one.
     */
    public function getOne(UserInterface $user, ObjectIdInterface $id): FileInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            '_id' => $id,
        ]);

        if ($result === null) {
            throw new Exception\NotFound('collection '.$id.' is not registered');
        }

        return $this->build($result, $user);
    }

    /**
     * Update.
     */
    public function update(CollectionInterface $node, array $data): bool
    {
        //$data['name'] = $resource->getName();
        $data['kind'] = $node->getKind();


             foreach ($data as $attribute => $value) {
                switch ($data) {
                    case 'name':
                        $node->setName($value);

                    break;
                    case 'meta':
                        $node->setMetaAttributes($value);

                    break;
                    case 'readonly':
                        $node->setReadonly($value);

                    break;
                    case 'filter':
                        if ($node instanceof Collection) {
                            $node->setFilter($value);
                        }

                    break;
                    case 'acl':
                        $node->setAcl($value);

                    break;
                    case 'lock':
                        if ($value === false) {
                            $node->unlock($lock);
                        } else {
                            $node->lock($lock);
                        }
                    break;
                }
            }


        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }



    /**
     * Build node instance.
     */
    public function build(array $node, ?UserInterface $user = null, ?CollectionInterface $parent=null): FileInterface
    {
        $id = $node['_id'];

        if (isset($node['shared']) && true === $node['shared'] && null !== $this->user && $node['owner'] != $this->user->getId()) {
            $node = $this->findReferenceNode($node);
        }

        if($parent === null) {
       // if (isset($node['parent'])) {
            $parent = $this->collection_factory->getOne($user, $node['parent']);
       // } elseif ($node['_id'] !== null) {
           // $parent = $this->getOne($user, null);
       // }
        }

        /*if (!array_key_exists('directory', $node)) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        $instance = $this->node_factory->build($this, $node, $parent);
         */

        return new File($node, $parent);
    }


    /**
     * Create new file as a child from this collection.
     */
    public function add(UserInterface $user, array $attributes, int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): File
    {
        $parent = $this->collection_factory->getOne($user, isset($attributes['parent']) ? new ObjectId($attributes['parent']) : null);

        if (!$this->acl->isAllowed($parent, 'w', $user)) {
            throw new ForbiddenException(
                'not allowed to create new node here',
                ForbiddenException::NOT_ALLOWED_TO_CREATE
            );
        }

        $this->emitter->emit('file.factory.preAdd', ...func_get_args());
        $name = $this->collection_factory->validateInsert($user, $parent, $attributes['name'], $conflict, File::class);

        if (isset($attributes['lock'])) {
            $attributes['lock'] = $this->prepareLock($attributes['lock']);
        }

        $id = new ObjectId();
            $meta = [
                '_id' => $id,
                'kind' => 'File',
                'pointer' => $id,
                'name' => $name,
                'deleted' => null,
            //    'parent' => $parent->getRealId(),
                'hash' => null,
                'mime' => MimeType::getType($name),
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
                'version' => 0,
//                'shared' => (true === $this->shared ? $this->getRealId() : $this->shared),
//                'storage_reference' => $this->getMount(),

                'shared' => (true === $parent->isShared() ? $parent->getRealId() : /*$parent->getShared()*/false),
                'storage' => $parent->getStorage()->createCollection($parent, $name),
                'storage_reference' => $parent->getMount(),
                'owner' => $user->getId(),
            ];

            /*if (null !== $this->user) {
                $meta['owner'] = $this->user->getId();
            }*/

            $session = $attributes['session'] ?? null;
            unset($attributes['session']);

            $save = array_merge($meta, $attributes);
            $save['parent'] = $parent->getRealId();

            $result = $this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $save);

            $this->logger->info('added new file ['.$save['_id'].'] under parent ['.$parent->getId().']', [
                'category' => get_class($this),
            ]);

            //$this->changed = $save['changed'];
            //$this->save('changed');
            //            $file = $this->fs->initNode($save);

            $file = $this->build($save, $user, $parent);

            if ($session !== null) {
                $file->setContent($session, $attributes);
            }

            $this->emitter->emit('file.factory.postAdd', ...array_merge([$result],func_get_args()));
            return $file;
    }


    /**
     * Completly remove file.
     */
    protected function _forceDelete(UserInterface $user, FileInterface $file): bool
    {
        try {
            $file->getParent()->getStorage()->forceDeleteFile($file);
            $this->cleanHistory();
            $this->resource_factory->delete($file->getId());

            $this->_logger->info('removed file node ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            $this->emitter->emit('file.factory.postDelete', func_get_args());
        } catch (\Exception $e) {
            $this->_logger->error('failed delete file node ['.$file->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }
}
