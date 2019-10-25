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
    public function copyTo(NodeInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = NodeInterface::DELETED_EXCLUDE): NodeInterface
    {
        if (null === $recursion) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->hook->run(
            'preCopyCollection',
            [$this, $parent, &$conflict, &$recursion, &$recursion_first]
        );

        if (NodeInterface::CONFLICT_RENAME === $conflict && $parent->childExists($this->name)) {
            $name = $this->getDuplicateName();
        } else {
            $name = $this->name;
        }

        if ($this->id === $parent->getId()) {
            throw new Exception\Conflict(
                'can not copy node into itself',
                Exception\Conflict::CANT_COPY_INTO_ITSELF
            );
        }

        if (NodeInterface::CONFLICT_MERGE === $conflict && $parent->childExists($this->name)) {
            $new_parent = $parent->getChild($this->name);

            if ($new_parent instanceof File) {
                $new_parent = $this;
            }
        } else {
            $new_parent = $parent->addDirectory($name, [
                'created' => $this->created,
                'changed' => $this->changed,
                'filter' => $this->filter,
                'meta' => $this->meta,
            ], NodeInterface::CONFLICT_NOACTION, true);
        }

        foreach ($this->getChildNodes($deleted) as $child) {
            $child->copyTo($new_parent, $conflict, $recursion, false, $deleted);
        }

        $this->hook->run(
            'postCopyCollection',
            [$this, $parent, $new_parent, $conflict, $recursion, $recursion_first]
        );

        return $new_parent;
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
        if (!$this->acl->isAllowed($file, 'w')) {
            throw new AclException\Forbidden(
                'not allowed to delete node '.$this->name,
                AclException\Forbidden::NOT_ALLOWED_TO_DELETE
            );
        }

        //$this->_hook->run('preDeleteFile', [$this, &$force, &$recursion, &$recursion_first]);

        if (true === $force || $this->isTemporaryFile($file)) {
            $result = $this->_forceDelete();
            $this->_hook->run('postDeleteFile', [$this, $force, $recursion, $recursion_first]);
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

        //$this->_hook->run('postDeleteFile', [$this, $force, $recursion, $recursion_first]);
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
        $parent = $this->collection_factory->getOne($user, isset($attributes['parent']['id']) ? new ObjectId($attributes['parent']['id']) : null);

        if (!$this->acl->isAllowed($parent, 'w')) {
            throw new ForbiddenException(
                'not allowed to create new node here',
                ForbiddenException::NOT_ALLOWED_TO_CREATE
            );
        }

//        $this->hook->run('preCreateFile', [$this, &$name, &$attributes, &$clone]);
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
                'deleted' => false,
            //    'parent' => $parent->getRealId(),
                'directory' => false,
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

 //           $this->hook->run('postCreateFile', [$this, $file, $clone]);

            return $file;
   }

    /**
     * Completely remove node.
     */
    protected function _forceDelete(?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->isReference() && !$this->isMounted() && !$this->isFiltered()) {
            $this->doRecursiveAction(function ($node) use ($recursion) {
                $node->delete(true, $recursion, false);
            }, NodeInterface::DELETED_INCLUDE);
        }

        try {
            $this->parent->getStorage()->forceDeleteCollection($this);
            $result = $this->db->storage->deleteOne(['_id' => $this->id]);

            if ($this->isShared()) {
                $result = $this->db->storage->deleteMany(['reference' => $this->id]);
            }

            $this->logger->info('force removed collection ['.$this->id.']', [
                'category' => get_class($this),
            ]);

            $this->hook->run(
                'postDeleteCollection',
                [$this, true, $recursion, $recursion_first]
            );
        } catch (\Exception $e) {
            $this->logger->error('failed force remove collection ['.$this->id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }
}
