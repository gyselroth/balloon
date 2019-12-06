<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Collection;

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
use Balloon\Node\Factory as NodeFactory;
use Balloon\User;
use Balloon\User\UserInterface;
use Balloon\Node\NodeInterface;
use Balloon\Collection;
use Normalizer;
use TaskScheduler\Process;
use TaskScheduler\Scheduler;
use Balloon\Async;

class Factory// extends AbstractNode implements CollectionInterface, IQuota
{
    public const COLLECTION_NAME = 'nodes';

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
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Storage.
     *
     * @var StorageAdapterInterface
     */
    protected $storage;

    /**
     * Storage Factory.
     *
     * @var StorageFactory
     */
    protected $storage_factory;

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
    public function __construct(Database $db, Emitter $emitter, ResourceFactory $resource_factory, LoggerInterface $logger, StorageAdapterInterface $storage, Acl $acl, StorageFactory $storage_factory, Scheduler $scheduler)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->emitter = $emitter;
        $this->storage = $storage;
        $this->acl = $acl;
        $this->storage_factory = $storage_factory;
        $this->resource_factory = $resource_factory;
        $this->scheduler = $scheduler;
    }

    public function setNodeFactory(NodeFactory $node_factory)
    {
        $this->node_factory = $node_factory;
    }

    /**
     * Copy node with children.
     */
    public function copyTo(UserInterface $user, CollectionInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = NodeInterface::DELETED_EXCLUDE): NodeInterface
    {
        if (null === $recursion) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->emitter->emit('collection.factory.preCopy', func_get_args());
        $exists = $this->childExists($user, $parent, $this->name);

        if (NodeInterface::CONFLICT_RENAME === $conflict && $exists === true) {
            $name = $this->getDuplicateName();
        } else {
            $name = $node->getName();
        }

        if ($node->getId() === $parent->getId()) {
            throw new Exception\Conflict(
                'can not copy node into itself',
                Exception\Conflict::CANT_COPY_INTO_ITSELF
            );
        }

        if (NodeInterface::CONFLICT_MERGE === $conflict && $exists === true) {
            $new_parent = $this->getChildByName($user, $parent, $node->getName());

            if ($new_parent instanceof FileInterface) {
                $new_parent = $parent;
            }
        } else {
            $attrs = $node->toArray();
            $new_parent = $this->add($user, $parent, [
                'name' => $name,
                'created' => $attrs['created'],
                'changed' => $attrs['changed'],
                'filter' => $attrs['filter'],
                'meta' => $attrs['meta'],
            ], NodeInterface::CONFLICT_NOACTION, true);
        }

        foreach ($this->getChildren($user, $node, $deleted) as $child) {
            $child->copyTo($new_parent, $conflict, $recursion, false, $deleted);
        }

        $this->emitter->emit('collection.factory.postCopy', ...array_merge([$new_parent], func_get_args()));
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

        return $this->_fs->findRawNode($this->getShareId())['share_name'];
    }

    /**
     * Fetch children items of this collection.
     *
     * Deleted:
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     */
    public function getChildren(UserInterface $user, CollectionInterface $collection,/*, int $deleted = NodeInterface::DELETED_EXCLUDE,*/ array $query = [], ?int $offset = null, ?int $limit = null, array $sort=[], bool $recursive = false): Generator
    {
        $query = $this->getChildrenFilter($user, $collection, $deleted=0, $query);

        if ($recursive === false) {
            return $this->node_factory->getAllQuery($user, $query, $offset, $limit);
        }

        unset($filter['parent']);

        return $this->node_factory->findNodesByFilterRecursive($this, $query, $offset, $limit);
    }


    /**
     * Fetch children items of this collection.
     */
    public function getChildByName(UserInterface $user, CollectionInterface $collection, string $name, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): NodeInterface
    {
        $name = $this->checkName($name);
        $filter = $this->getChildrenFilter($user, $collection, $deleted, $filter);
        $filter['name'] = new Regex('^'.preg_quote($name).'$', 'i');
        $result = $this->db->{self::COLLECTION_NAME}->findOne($filter);

        if ($result === null) {
            throw new Exception\NotFound('collection '.$name.' does not exists');
        }

        return $this->build($result, $user);
    }

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     */
    public function deleteOne(UserInterface $user, CollectionInterface $collection, bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$collection->isReference() && !$this->acl->isAllowed($collection, 'w', $user)) {
            throw new ForbiddenException(
                'not allowed to delete node '.$this->name,
                ForbiddenException::NOT_ALLOWED_TO_DELETE
            );
        }

        if (null === $recursion) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->emitter->emit('collection.factory.preDelete', func_get_args());

        if (true === $force) {
            $result = $this->_forceDelete($user, $collection, $recursion, $recursion_first);
            $this->emitter->emit('collection.factory.postDelete', func_get_args());
            return $result;
        }

        //$this->deleted = new UTCDateTime();
        $storage = $collection->getParent()->getStorage()->deleteCollection($collection);

        if (!$collection->isReference() && !$collection->isMounted() && !$collection->isFiltered()) {
            $that = $this;
            $this->doRecursiveAction($user, $collection, function ($node) use ($that, $recursion) {
                $that->node_factory->deleteOne($node, false, $recursion, false);
            }, NodeInterface::DELETED_EXCLUDE);
        }

        /*if (null !== $this->_id) {
            $result = $this->save([
                'deleted', 'storage',
            ], [], $recursion, false);
        } else {
            $result = true;
        }*/

        $result = $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $collection, [
            'deleted' => new UTCDateTime(),
            'storage' => $storage,
        ]);

        $this->emitter->emit('collection.factory.preDelete', ...array_merge([$result],func_get_args()));
        return $result;
    }

    /**
     * Check if this collection has child named $name.
     *
     * deleted:
     *
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     *
     * @param string $name
     * @param int    $deleted
     */
    public function childExists(UserInterface $user, CollectionInterface $parent, string $name, $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): bool
    {
        $name = $this->checkName($name);

        $find = [
            'parent' => $parent->getRealId(),
            'name' => new Regex('^'.preg_quote($name).'$', 'i'),
        ];
        //if (null !== $this->_user) {
        $find['owner'] = $user->getId();
        //}

        /*switch ($deleted) {
            case NodeInterface::DELETED_EXCLUDE:
                $find['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $find['deleted'] = ['$type' => 9];

                break;
        }*/

        $find = array_merge($filter, $find);

        if ($parent->isSpecial()) {
            unset($find['owner']);
        }

        return $this->db->{self::COLLECTION_NAME}->count($find) > 0;
    }

    /**
     * Share collection.
     */
    public function share(array $acl, string $name): bool
    {
        if ($this->isShareMember()) {
            throw new Exception('a sub node of a share can not be shared');
        }

        $this->checkName($name);
        $this->acl->validateAcl($this->_server, $acl);

        $action = [
            '$set' => [
                'shared' => $this->getRealId(),
            ],
        ];

        $query = [
            '$or' => [
                ['reference' => ['exists' => true]],
                ['shared' => true],
            ],
        ];

        if (iterator_count($this->_fs->findNodesByFilterRecursive($this, $query, 0, 1)) !== 0) {
            throw new Exception\Conflict(
                'folder contains a shared folder',
                Exception\Conflict::NODE_CONTAINS_SHARED_NODE
            );
        }

        $toset = $this->_fs->findNodesByFilterRecursiveToArray($this);
        $this->db->storage->updateMany([
            '_id' => [
                '$in' => $toset,
            ],
        ], $action);

        $this->db->delta->updateMany([
            '_id' => [
                '$in' => $toset,
            ],
        ], $action);

        if ($this->getRealId() === $this->_id) {
            $this->acl = $acl;
            $this->shared = true;
            $this->share_name = $name;
            $this->save(['acl', 'shared', 'share_name']);
        } else {
            $this->db->storage->updateOne([
                '_id' => $this->getRealId(),
            ], [
                '$set' => [
                    'share_name' => $name,
                    'acl' => $acl,
                ],
            ]);
        }

        return true;
    }

    /**
     * Unshare collection.
     */
    public function unshare(): bool
    {
        if (!$this->acl->isAllowed($this, 'm')) {
            throw new ForbiddenException(
                'not allowed to share node',
                ForbiddenException::NOT_ALLOWED_TO_MANAGE
            );
        }

        if (true !== $this->shared) {
            throw new Exception\Conflict(
                'Can not unshare a none shared collection',
                Exception\Conflict::NOT_SHARED
            );
        }

        $this->shared = false;
        $this->share_name = null;
        $this->acl = [];
        $action = [
            '$set' => [
                'owner' => $this->_user->getId(),
                'shared' => false,
            ],
        ];

        $toset = $this->_fs->findNodesByFilterRecursiveToArray($this);
        $this->db->storage->updateMany([
            '_id' => [
                '$in' => $toset,
            ],
        ], $action);

        $result = $this->save(['shared'], ['acl', 'share_name']);

        return true;
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
     * Change stream.
     */
    public function watch(UserInterface $user, ?ObjectIdInterface $after = null, bool $existing = true, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $filter = $this->prepareQuery($user, $query);
        $that = $this;

        return $this->resource_factory->watchFrom($this->db->{self::COLLECTION_NAME}, $after, $existing, $filter, function (array $resource) use ($user, $that) {
            return $that->build($resource, $user);
        }, $offset, $limit, $sort);
    }

    /**
     * Get one.
     */
    public function getOne(UserInterface $user, ?ObjectIdInterface $id): CollectionInterface
    {
        if($id === null) {
            return new Collection([
                'owner' => $user->getId(),
                'readonly' => false,
            ], null, $this->storage);
        }


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
    public function update(UserInterface $user, CollectionInterface $node, array $data): ?Process
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
                case 'filter':
                    $node->setFilter($value);
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

        //$node->set($data);
        $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $node, $node->toArray());
        return $result;
    }

    /**
     * Set the name.
     */
    public function setName(UserInterface $user, NodeInterface $node, string $name): NodeInterface
    {
        $name = $this->checkName($name);

        try {
            $child = $this->getChildByName($user, $node->getParent(), $name);
            if ($child->getId() != $node->getId()) {
                throw new Exception\NotUnique(
                    'a node called '.$name.' does already exists in this collection',
                );
            }
        } catch (Exception\NotFound $e) {
            //child does not exists, we can safely rename
        }

        $node->setName($name);
        return $node;
    }



    /**
     * Create new directory.
     */
    public function add(UserInterface $user, array $attributes, int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): CollectionInterface
    {
        $parent = $this->getOne($user, isset($attributes['parent']) ? new ObjectId($attributes['parent']) : null);

        if (!$this->acl->isAllowed($parent, 'w', $user)) {
            throw new ForbiddenException(
                'not allowed to create new node here',
                ForbiddenException::NOT_ALLOWED_TO_CREATE
            );
        }

        $this->emitter->emit('collection.factory.preAdd', ...func_get_args());
        $name = $this->validateInsert($user, $parent, $attributes['name'], $conflict, Collection::class);

        if (isset($attributes['lock'])) {
            $attributes['lock'] = $this->prepareLock($attributes['lock']);
        }

        $id = new ObjectId();

        $meta = [
                '_id' => $id,
                'kind' => 'Collection',
                'pointer' => $id,
                'name' => $name,
                'deleted' => null,
                'mime' => 'inode/directory',
              //  'parent' => $parent->getRealId(),
              //  'directory' => true,
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
                'shared' => (true === $parent->isShared() ? $parent->getRealId() : /*$parent->getShared()*/false),
                'storage' => $parent->getStorage()->createCollection($parent, $name),
                'storage_reference' => $parent->getMount(),
                'owner' => $user->getId(),
            ];

            /*if (null !== $this->_user) {
                $meta['owner'] = $this->_user->getId();
            }*/

            $save = array_merge($meta, $attributes);
            $save['parent'] = $parent->getRealId();

            if (isset($save['filter'])) {
                $this->validateFilter($save['filter']);
            }

            if (isset($save['acl'])) {
                $this->validateAcl($save['acl']);
            }

            $result = $this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $save);

            $this->logger->info('added new collection ['.$save['_id'].'] under parent ['.$parent->getId().']', [
                'category' => get_class($this),
            ]);

//TODO save parent change
//$this->changed = $save['changed'];
//$this->save('changed');

            $new = $this->build($save, $user, $parent);
            $this->emitter->emit('collection.factory.postAdd', ...array_merge([$new], func_get_args()));

            return $new;
    }

        /**
     * Get by id.
     */
    protected function getStorage(ObjectId $node, array $mount): StorageAdapterInterface
    {
        $id = (string) $node;

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        return $this->cache[$id] = $this->storage_factory->build($mount);
    }

        /**
     * Prepare query.
     */
    public function prepareQuery(UserInterface $user, ?array $query = null): array
    {
        $filter = [
            'kind' => 'Collection',
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
     * Build node instance.
     */
    public function build(array $node, ?UserInterface $user = null, ?CollectionInterface $parent=null): CollectionInterface
    {
        $id = $node['_id'];

        if (isset($node['shared']) && true === $node['shared'] && null !== $this->user && $node['owner'] != $this->user->getId()) {
            $node = $this->findReferenceNode($node);
        }

        if($parent === null) {
        //if (isset($node['parent'])) {
            $parent = $this->getOne($user, $node['parent']);
        /*} elseif ($node['_id'] !== null) {
            $parent = $this->getOne($user, null);
            }*/
        }

        /*if (!array_key_exists('directory', $node)) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        $instance = $this->node_factory->build($this, $node, $parent);
         */

        $storage = $this->storage;

        if (isset($node['reference'])) {
            $share = $fs->findRawNode($node['reference']);
            if (isset($share['mount'])) {
                $storage = $this->getStorage($share['_id'], $share['mount']);
            } elseif (isset($share['storage_reference'])) {
                $external = $fs->findRawNode($share['storage_reference'])['mount'];
                $storage = $this->getStorage($share['storage_reference'], $external);
            }
        } elseif (isset($node['storage_reference'])) {
            $external = $fs->findRawNode($node['storage_reference'])['mount'];
            $storage = $this->getStorage($node['storage_reference'], $external);
        } elseif (isset($node['mount'])) {
            $storage = $this->getStorage($node['_id'], $node['mount']);
        }

        $resource = new Collection($node, $parent, $storage);

        if (!$this->acl->isAllowed($resource, 'r')) {
            if ($instance->isReference()) {
                $instance->delete(true);
            }

            throw new ForbiddenException(
                'not allowed to access node',
                ForbiddenException::NOT_ALLOWED_TO_ACCESS
            );
        }

        return $resource;
    }


    /**
     * Do recursive Action.
     */
    public function doRecursiveAction(UserInterface $user, CollectionInterface $collection, callable $callable, int $deleted = NodeInterface::DELETED_EXCLUDE): bool
    {
        $children = $this->getChildren($user, $collection, /*$deleted,*/ []);

        foreach ($children as $child) {
            $callable($child);
        }

        return true;
    }




    //TODO
    const MAX_NAME_LENGTH = 255;
    /**
     * Check name.
     */
    public function checkName(string $name): string
    {
        if (preg_match('/([\\\<\>\:\"\/\|\*\?])|(^$)|(^\.$)|(^\..$)/', $name)) {
            throw new Exception\InvalidArgument('name contains invalid characters');
        }
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            throw new Exception\InvalidArgument('name is longer than '.self::MAX_NAME_LENGTH.' characters');
        }

        if (!Normalizer::isNormalized($name)) {
            $name = Normalizer::normalize($name);
        }

        return $name;
    }


    /**
     * Move node.
     */
    public function moveTo(UserInterface $user, CollectionInterface $node, CollectionInterface $parent, int $conflict = NodeInterface::CONFLICT_NOACTION): NodeInterface
    {
        //TODO drop support in balloon v4
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

        if (!$this->acl->isAllowed($node, 'w', $user) && !$node->isReference()) {
            throw new ForbiddenException(
                'not allowed to move node '.$node->getName(),
                ForbiddenException::NOT_ALLOWED_TO_MOVE
            );
        }

        $new_name = $this->validateInsert($user, $node, $node->getName(), $conflict, get_class($node));

        if ($node->isShared() && $parent->isSpecial()) {
            throw new Exception\Conflict(
                'a shared folder can not be a child of a shared folder',
                Exception\Conflict::SHARED_NODE_CANT_BE_CHILD_OF_SHARE
            );
        }

        if (NodeInterface::CONFLICT_RENAME === $conflict && $new_name !== $this->name) {
            $this->setName($user, $node, $new_name);
            //$this->raw_attributes['name'] = $this->name;
        }

        /*if ($this instanceof Collection) {
            $query = [
                '$or' => [
                    ['reference' => ['exists' => true]],
                    ['shared' => true],
                ],
            ];

            if ($parent->isShared() && iterator_count($this->_fs->findNodesByFilterRecursive($this, $query, 0, 1)) !== 0) {
                throw new Exception\Conflict(
                    'folder contains a shared folder',
                    Exception\Conflict::NODE_CONTAINS_SHARED_NODE
                );
            }
        }*/

        /*Fixed above
         *
         * if ($this->isShared() && $parent->isSpecial()) {
            throw new Exception\Conflict(
                'a shared folder can not be an indirect child of a shared folder',
                Exception\Conflict::SHARED_NODE_CANT_BE_INDIRECT_CHILD_OF_SHARE
            );
        }*/

        if (($parent->isSpecial() && $node->getShareId() != $parent->getShareId())
          || (!$parent->isSpecial() && $node->isShareMember())
          || ($parent->getMount() != $node->getParent()->getMount())) {
            $new = $this->copyTo($user, $node, $parent, $conflict);
            $this->deleteOne($user, $node);

            return $new;
        }

        if ($parent->childExists($this->name) && NodeInterface::CONFLICT_MERGE === $conflict) {
            $new = $this->copyTo($parent, $conflict);
            $this->deleteOne($user, $node, true);

            return $new;
        }

        $node->setParent($user, $parent);
        $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $node, $node->toArray());
        return $node;
    }


    /**
     * Validate insert.
     */
    public function validateInsert(UserInterface $user, ?CollectionInterface $parent, string $name, int $conflict = NodeInterface::CONFLICT_NOACTION, string $type = Collection::class): string
    {
        if ($parent->isReadonly()) {
            throw new Exception\Readonly('node is set as readonly, it is not possible to add new sub nodes');
        }

        if ($parent->isFiltered()) {
            throw new Exception\FilteredParent('could not add node '.$name.' into a filtered parent collection');
        }

        if ($parent->isDeleted()) {
            throw new Exception\NotFound('could not add node '.$name.' into a deleted parent collection');
        }

        $name = $this->checkName($name);

        if ($this->childExists($user, $parent, $name)) {
            if (NodeInterface::CONFLICT_NOACTION === $conflict) {
                throw new Exception\NotUnique('a node called '.$name.' does already exists in this collection');
            }
            if (NodeInterface::CONFLICT_RENAME === $conflict) {
                $name = $this->getDuplicateName($name, $type);
            }
        }

        return $name;
    }

    /**
     * Validate filtered collection query.
     */
    protected function validateFilter(string $filter): bool
    {
        $filter = toPHP(fromJSON($filter), [
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        ]);

        $this->db->{self::COLLECTION_NAME}->count($filter);

        return true;
    }

    /**
     * Validate acl.
     */
    protected function validateAcl(UserInterface $user, CollectionInterface $collection, array $acl): bool
    {
        if (!$this->acl->isAllowed($collection, 'm', $user)) {
            throw new ForbiddenException(
                'not allowed to set acl',
                ForbiddenException::NOT_ALLOWED_TO_MANAGE
            );
        }

        if (!$collection->isSpecial()) {
            throw new Exception\Conflict('node acl may only be set on share member nodes', Exception\Conflict::NOT_SHARED);
        }

        $this->acl->validateAcl($acl);

        return true;
    }

    /**
     * Get children query filter.
     *
     * Deleted:
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     */
    protected function getChildrenFilter(UserInterface $user, CollectionInterface $collection, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): array
    {
        $search = [
            'parent' => $collection->getRealId(),
        ];

        /*if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $search['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $search['deleted'] = ['$type' => 9];
        }*/

        $search = array_merge($filter, $search);

        if ($collection->isShared()) {
            $search = [
                '$and' => [
                    $search,
                    [
                        '$or' => [
                            ['shared' => $collection->getReference()],
                            ['shared' => $collection->getShare()],
                            ['shared' => $collection->getId()],
                        ],
                    ],
                ],
            ];
        }/* elseif (null !== $this->_user) {
            $search['owner'] = $this->_user->getId();
        }*/
        else {
            $search['owner'] = $user->getId();
        }

        if ($collection->isFiltered() /*&& $this->_user !== null*/) {
            $stored = toPHP(fromJSON($collection->getFilter()), [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);

            $include = isset($search['deleted']) ? ['deleted' => $search['deleted']] : [];
            $stored_filter = ['$and' => [
                array_merge(
                    $include,
                    $stored,
                    $filter
                ),
                ['$or' => [
                    ['owner' => $user->getId()],
                    ['shared' => ['$in' => $user->getShares()]],
                ]],
                [
                    '_id' => ['$ne' => $collection->getId()],
                ],
            ]];

            $search = ['$or' => [
                $search,
                $stored_filter,
            ]];
        }

        return $search;
    }

    /**
     * Completely remove node.
     */
    protected function _forceDelete(UserInterface $user, CollectionInterface $collection, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$collection->isReference() && !$collection->isMounted() && !$collection->isFiltered()) {
            $this->doRecursiveAction(function ($node) use ($recursion) {
                $node->delete(true, $recursion, false);
            }, NodeInterface::DELETED_INCLUDE);
        }

        try {
            $collection->getParent()->getStorage()->forceDeleteCollection($collection);
            $this->resource_factory->deleteFrom($this->db->{self::COLLECTION_NAME}, $collection->getId());

            if ($collection->isShared()) {
                $this->db->{self::COLLECTION_NAME}->deleteMany(['reference' => $collection->getId()]);
            }

            $this->logger->info('force removed collection ['.$collection->getId().']', [
                'category' => get_class($this),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('failed force remove collection ['.$collection->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }
}
