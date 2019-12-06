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
use Balloon\Session\Factory as SessionFactory;
use Balloon\Session\SessionInterface;
use TaskScheduler\Scheduler;
use TaskScheduler\Process;
use Balloon\Async;

class Factory
{
    public const COLLECTION_NAME = 'nodes';


    /**
     * History types.
     */
    public const HISTORY_CREATE = 0;
    public const HISTORY_EDIT = 1;
    public const HISTORY_RESTORE = 2;

    /**
     * Empty content hash (NULL).
     */
    public const EMPTY_CONTENT = 'd41d8cd98f00b204e9800998ecf8427e';

    /**
     * Temporary file patterns.
     *
     * @var array
     **/
    public CONST TEMP_FILES = [
        '/^\._(.*)$/',     // OS/X resource forks
        '/^.DS_Store$/',   // OS/X custom folder settings
        '/^desktop.ini$/', // Windows custom folder settings
        '/^Thumbs.db$/',   // Windows thumbnail cache
        '/^.(.*).swpx$/',  // ViM temporary files
        '/^.(.*).swx$/',   // ViM temporary files
        '/^.(.*).swp$/',   // ViM temporary files
        '/^\.dat(.*)$/',   // Smultron seems to create these
        '/^~lock.(.*)#$/', // Windows 7 lockfiles
        '/^\~\$/',         // Temporary office files
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
     * Max file versions
     */
    protected $max_version = 30;

    /**
     * Initialize.
     */
    public function __construct(Database $db, Emitter $emitter, ResourceFactory $resource_factory, LoggerInterface $logger, Acl $acl, CollectionFactory $collection_factory, SessionFactory $session_factory, Scheduler $scheduler)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->emitter = $emitter;
        $this->acl = $acl;
        $this->resource_factory = $resource_factory;
        $this->collection_factory = $collection_factory;
        $this->session_factory = $session_factory;
        $this->scheduler = $scheduler;
    }

    /**
     * Copy node with children.
     */
    public function copyTo(UserInterface $user, FileInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = NodeInterface::DELETED_EXCLUDE): NodeInterface
    {
        $this->emitter->emit('file.factory.preCopy', ...func_get_args());

        if (NodeInterface::CONFLICT_RENAME === $conflict && $this->collection_factory->childExists($user, $parent, $node->getName())) {
            $name = $this->getDuplicateName();
        } else {
            $name = $this->name;
        }

        if (NodeInterface::CONFLICT_MERGE === $conflict && $this->collection_factory->childExists($user, $parent, $node)) {
            $result = $this->node_factory->getChildByName($user, $parent, $node->getName());

            if ($result instanceof CollectionInterface) {
                $result = $this->copyToCollection($user, $node, $result, $name);
            } else {
                $stream = $this->openReadStream();
                if ($stream !== null) {
                    $result->put($stream);
                }
            }
        } else {
            $result = $this->copyToCollection($user, $node, $parent, $name);
        }

        $this->emitter->emit('file.factory.postCopy', ...array_merge([$result],func_get_args()));
        return $result;
    }


    /**
     * Move node.
     */
    public function moveTo(UserInterface $user, FileInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION): FileInterface
    {
        $this->emitter->emit('file.factory.preMove', ...func_get_args());

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

        $new_name = $this->collection_factory->validateInsert($user, $parent, $node->getName(), $conflict, get_class($node));

        if (NodeInterface::CONFLICT_RENAME === $conflict && $new_name !== $this->name) {
            $this->setName($new_name);
            $this->raw_attributes['name'] = $this->name;
        }

        if (($parent->isSpecial() && $this->shared != $parent->getShareId())
          || (!$parent->isSpecial() && $node->isShareMember())
          || ($parent->getMount() != $node->getParent()->getMount())) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete($user, $node);

            return $new;
        }

        if ($this->collection_factory->childExists($user, $parent, $node->getName()) && NodeInterface::CONFLICT_MERGE === $conflict) {
            $new = $this->copyTo($user, $node, $parent, $conflict);
            $this->delete($user, $node, true);

            return $new;
        }


        $data = [
            'storage' => $node->getParent()->getStorage()->move($node, $parent),
            'parent' => $parent->getRealId(),
            'owner' => $user->getId(),
        ];

        $node->set($data);
        $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $node, $data);

        $this->emitter->emit('file.factory.postMove', ...func_get_args());
        return $node;
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

        if (true === $force || $file->isTemporary()) {
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
    public function update(UserInterface $user, FileInterface $node, array $data): ?Process
    {
        $data['kind'] = $node->getKind();
        $result = null;

        $orig = $node->toArray();

        foreach ($data as $attribute => $value) {
            if(($orig[$attribute] ?? null) == $value) {
                continue;
            }

             switch ($attribute) {
                case 'parent':
                    $result = $this->scheduler->addJob(Async\MoveNode::class, [
                        'owner' => $user->getId(),
                        'node' => $node->getId(),
                        'parent' => $value === null ? null : new ObjectId($value),
                    ]);
                break;
                case 'name':
                    $this->setName($user, $node, $value);

                break;
                case 'readonly':
                    $node->setReadonly($value);

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

        //$node->set($data);
        $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $node, $node->toArray());
        return $result;
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
        $session = $this->session_factory->getOne($user, isset($attributes['session']) ? new ObjectId($attributes['session']) : null);

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
            //    'created' => new UTCDateTime(),
            //    'changed' => new UTCDateTime(),
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

            $file = $this->build($result, $user, $parent);

            if ($session !== null) {
                $this->setContent($file, $user, $session);
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

            $this->logger->info('removed file node ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            $this->emitter->emit('file.factory.postDelete', func_get_args());
        } catch (\Exception $e) {
            $this->logger->error('failed delete file node ['.$file->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }

    /**
     * Restore content to some older version.
     */
    public function restore(int $version): bool
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new AclException\Forbidden('not allowed to restore node '.$this->name, AclException\Forbidden::NOT_ALLOWED_TO_RESTORE);
        }

        $this->_hook->run('preRestoreFile', [$this, &$version]);

        if ($this->readonly) {
            throw new Exception\Conflict('node is marked as readonly, it is not possible to change any content', Exception\Conflict::READONLY);
        }

        if ($this->version === $version) {
            throw new Exception('file is already version '.$version);
        }

        $current = $this->version;

        $v = array_search($version, array_column($this->history, 'version'), true);
        if (false === $v) {
            throw new Exception('failed restore file to version '.$version.', version was not found');
        }

        $file = $this->history[$v]['storage'];
        $latest = $this->version + 1;

        $this->history[] = [
            'version' => $latest,
            'changed' => $this->changed,
            'user' => $this->owner,
            'type' => self::HISTORY_RESTORE,
            'hash' => $this->history[$v]['hash'],
            'origin' => $this->history[$v]['version'],
            'storage' => $this->history[$v]['storage'],
            'size' => $this->history[$v]['size'],
            'mime' => isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : $this->mime,
        ];

        try {
            $this->deleted = false;
            $this->storage = $this->history[$v]['storage'];

            $this->hash = null === $file ? self::EMPTY_CONTENT : $this->history[$v]['hash'];
            $this->mime = isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : $this->mime;
            $this->size = $this->history[$v]['size'];
            $this->changed = $this->history[$v]['changed'];
            $new = $this->increaseVersion();
            $this->version = $new;

            $this->save([
                'deleted',
                'version',
                'storage',
                'hash',
                'mime',
                'size',
                'history',
                'changed',
            ]);

            $this->_hook->run('postRestoreFile', [$this, &$version]);

            $this->_logger->info('restored file ['.$this->_id.'] to version ['.$version.']', [
                'category' => get_class($this),
            ]);
        } catch (\Exception $e) {
            $this->_logger->error('failed restore file ['.$this->_id.'] to version ['.$version.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }

    /**
     * Delete version.
     */
    public function deleteVersion(FileInterface $file, int $version): array
    {
        $history = $file->getHistory();
        $key = array_search($version, array_column($history, 'version'), true);

        if (false === $key) {
            throw new Exception('version '.$version.' does not exists');
        }

        $blobs = array_column($history, 'storage');

        try {
            //do not remove blob if there are other versions linked against it
            if ($history[$key]['storage'] !== null && count(array_keys($blobs, $history[$key]['storage'])) === 1) {
                $file->getParent()->getStorage()->forceDeleteFile($file, $version);
            }

            array_splice($history, $key, 1);

            $this->logger->debug('removed version ['.$version.'] from file ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            return $history;
        } catch (StorageException\BlobNotFound $e) {
            $this->_logger->error('failed remove version ['.$version.'] from file ['.$file->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            return $history;
        }
    }

    /**
     * Cleanup history.
     */
    public function cleanHistory(FileInterface $file): bool
    {
        foreach ($this->history as $node) {
            $this->deleteVersion($file, $node['version']);
        }

        return true;
    }

    /**
     * Set content (temporary file).
     */
    public function setContent(FileInterface $file, UserInterface $user, SessionInterface $session): int
    {
        $this->logger->debug('set temporary file ['.$session->getId().'] as file content for ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        $previous = $file->getVersion();
        $storage = $file->getParent()->getStorage();

        if (!$this->acl->isAllowed($file, 'w')) {
            throw new AclException\Forbidden('not allowed to modify node', AclException\Forbidden::NOT_ALLOWED_TO_MODIFY);
        }

        $this->emitter->emit('file.factory.preSetContent', ...func_get_args());

        if ($file->isReadonly()) {
            throw new Exception\Conflict('node is marked as readonly, it is not possible to change any content', Exception\Conflict::READONLY);
        }

        $result = $storage->storeFile($file, $session);
        $data = [];
        $data['deleted'] = null;

        $hash = $session->getHash();

        if ($file->getHash() === $hash) {
            $this->logger->debug('do not update file version, hash identical to existing version ['.$this->file->getHash().' == '.$hash.']', [
                'category' => get_class($this),
            ]);

            return $file->getVersion();
        }

        $data['hash'] = $hash;
        $data['size'] = $session->getSize();

        if ($data['size'] === 0 && $file->getMount() === null) {
            $data['storage'] = null;
        } else {
            $data['storage'] = $result['reference'];
        }

        $data = array_merge($data, $this->increaseVersion($file));
        if ($result['reference'] != $storage || $previous === 0) {
            $data['history'] = $this->addVersion($file, $user, $data['history']);
        }

        $file->set($data);
        $this->session_factory->deleteOne($user, $session->getId());
        $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $file, $data);
        $this->emitter->emit('file.factory.postSetContent', ...func_get_args());

        return $data['version'];
    }

    /**
     * Copy to collection.
     */
    protected function copyToCollection(UserInterface $user, FileInterface $file, CollectionInterface $parent, string $name): FileInterface
    {
        $result = $parent->addFile($name, null, [
            'created' => $file->getCreated(),
            'changed' => $file->getChanged(),
            'meta' => $file->getMeta(),
        ], NodeInterface::CONFLICT_NOACTION, true);

        $stream = $file->openReadStream();

        if ($stream !== null) {
            $session = $this->session_factory->add($user, $parent, $stream);
            $result->setContent($session);
            fclose($stream);
        }

        return $result;
    }

    /**
     * Increase version.
     */
    protected function increaseVersion(FileInterface $file): array
    {
        $data = [
            'version' => $file->getVersion(),
            'history' => $file->getHistory(),
        ];

        if (count($data['history']) >= $this->max_version) {
            $del = key($history);

            $this->logger->debug('history limit ['.$this->max_version.'] reached, remove oldest version ['.$data['history'][$del]['version'].'] from file ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            $data['history'] = $this->deleteVersion($file, $data['history'][$del]['version']);
        }

        $data['version']++;
        return $data;
    }

    /**
     * Add new version.
     */
    protected function addVersion(FileInterface $file, UserInterface $user, array $history): array
    {
        if (1 !== $file->getVersion()) {
            $this->logger->debug('added new history version ['.$file->getVersion().'] for file ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            $history[] = [
                'version' => $file->getVersion(),
                'changed' => $file->getChanged(),
                'user' => $user->getId(),
                'type' => self::HISTORY_EDIT,
                'storage' => $file->getStorageReference(),
                'size' => $file->getSize(),
                'mime' => $file->getContentType(),
                'hash' => $file->getHash(),
            ];

            return $history;
        }

        $this->logger->debug('added first file version [1] for file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        $history[0] = [
            'version' => 1,
            'changed' => $file->getChanged(),//isset($attributes['changed']) ? $attributes['changed'] : new UTCDateTime(),
            'user' => $user->getId(),
            'type' => self::HISTORY_CREATE,
            'storage' => $file->getStorageReference(),
            'size' => $file->getSize(),
            'mime' => $file->getContentType(),
            'hash' => $file->getHash(),
        ];

        return $history;
    }
}
