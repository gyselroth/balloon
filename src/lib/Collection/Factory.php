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
use Normalizer;;


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
    public function __construct(Database $db, Emitter $emitter, ResourceFactory $resource_factory, LoggerInterface $logger, StorageAdapterInterface $storage, Acl $acl, StorageFactory $storage_factory)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->emitter = $emitter;
        $this->storage = $storage;
        $this->acl = $acl;
        $this->storage_factory = $storage_factory;
        $this->resource_factory = $resource_factory;
    }

    public function setNodeFactory(NodeFactory $node_factory)
    {
        $this->node_factory = $node_factory;
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

        $this->_hook->run(
            'preCopyCollection',
            [$this, $parent, &$conflict, &$recursion, &$recursion_first]
        );

        if (NodeInterface::CONFLICT_RENAME === $conflict && $parent->childExists($this->name)) {
            $name = $this->getDuplicateName();
        } else {
            $name = $this->name;
        }

        if ($this->_id === $parent->getId()) {
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

        $this->_hook->run(
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
        $query = $this->getChildrenFilter($collection, $deleted=0, $query);

        if ($recursive === false) {
            return $this->node_factory->getAllQuery($user, $query, $offset, $limit);
        }

        unset($filter['parent']);

        return $this->node_factory->findNodesByFilterRecursive($this, $query, $offset, $limit);
    }




    /**
     * Fetch children items of this collection.
     */
    public function getChild($name, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): NodeInterface
    {
        $name = $this->checkName($name);
        $filter = $this->getChildrenFilter($deleted, $filter);
        $filter['name'] = new Regex('^'.preg_quote($name).'$', 'i');
        $node = $this->db->storage->findOne($filter);

        if (null === $node) {
            throw new Exception\NotFound(
                'node called '.$name.' does not exists here',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $this->logger->debug('loaded node ['.$node['_id'].' from parent node ['.$this->getRealId().']', [
            'category' => get_class($this),
        ]);

        return $this->_fs->initNode($node);
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

        /*$this->_hook->run(
            'preDeleteCollection',
            [$this, &$force, &$recursion, &$recursion_first]
        );*/

        if (true === $force) {
            return $this->_forceDelete($recursion, $recursion_first);
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

        /*$this->_hook->run(
            'postDeleteCollection',
            [$this, $force, $recursion, $recursion_first]
        );*/

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
    public function childExists(UserInterface $user, CollectionInterface $parent, $name, $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): bool
    {
        $name = $this->checkName($name);

        $find = [
            'parent' => $parent->getRealId(),
            'name' => new Regex('^'.preg_quote($name).'$', 'i'),
        ];

        //if (null !== $this->_user) {
        $find['owner'] = $user->getId();
        //}

        switch ($deleted) {
            case NodeInterface::DELETED_EXCLUDE:
                $find['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $find['deleted'] = ['$type' => 9];

                break;
        }

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
     * Get one.
     */
    public function getOne(UserInterface $user, ?ObjectIdInterface $id): CollectionInterface
    {
        if($id === null) {
            return new Collection([
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
     * Delete by name.
     */
   /* public function deleteOne(UserInterface $user, CollectionInterface $collection): bool
    {
        return $this->resource_factory->deleteFrom($this->db->{self::COLLECTION_NAME}, $collection->getId());
   }*/


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
     * Create new directory.
     */
    public function add(User $user, array $attributes,/*$name, array $attributes = [],*/ int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): CollectionInterface
    {
        $parent = $this->getOne($user, isset($attributes['parent']['id']) ? new ObjectId($attributes['parent']['id']) : null);

        /*if (!$this->acl->isAllowed($this, 'w')) {
            throw new ForbiddenException(
                'not allowed to create new node here',
                ForbiddenException::NOT_ALLOWED_TO_CREATE
            );
        }*/
       # var_dump($parent);
       # var_dump($parent->getRealId());

        //$this->_hook->run('preCreateCollection', [$this, &$name, &$attributes, &$clone]);
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
                'deleted' => false,
              //  'parent' => $parent->getRealId(),
                'directory' => true,
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

            $this->logger->info('added new collection ['.$save['_id'].'] under parent ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

//TODO save parent change
//$this->changed = $save['changed'];
//$this->save('changed');

            $new = $this->build($save, $user, $parent);
     //       $this->_hook->run('postCreateCollection', [$this, $new, $clone]);

            return $new;
            //return $new;
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

        return new Collection($node, $parent, $storage);
    }


    /**
     * Do recursive Action.
     */
    public function doRecursiveAction(UserInterface $user, CollectionInterface $collection, callable $callable, int $deleted = NodeInterface::DELETED_EXCLUDE): bool
    {
        $children = $this->getChildNodes($user, $collection, $deleted, []);

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
     * Validate insert.
     */
    public function validateInsert(UserInterface $user, ?Collection $parent, string $name, int $conflict = NodeInterface::CONFLICT_NOACTION, string $type = Collection::class): string
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

        $this->db->storage->findOne($filter);

        return true;
    }

    /**
     * Validate acl.
     */
    protected function validateAcl(array $acl): bool
    {
        if (!$this->acl->isAllowed($this, 'm')) {
            throw new ForbiddenException(
                'not allowed to set acl',
                ForbiddenException::NOT_ALLOWED_TO_MANAGE
            );
        }

        if (!$this->isSpecial()) {
            throw new Exception\Conflict('node acl may only be set on share member nodes', Exception\Conflict::NOT_SHARED);
        }

        $this->acl->validateAcl($this->_server, $acl);

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
    protected function getChildrenFilter(CollectionInterface $collection, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): array
    {
        $search = [
            'parent' => $collection->getRealId(),
        ];

        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $search['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $search['deleted'] = ['$type' => 9];
        }

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
        } elseif (null !== $this->_user) {
            $search['owner'] = $this->_user->getId();
        }

        if ($collection->isFiltered() !== null && $this->_user !== null) {
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
                    ['owner' => $this->_user->getId()],
                    ['shared' => ['$in' => $this->_user->getShares()]],
                ]],
                [
                    '_id' => ['$ne' => $this->_id],
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
    protected function _forceDelete(?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->isReference() && !$this->isMounted() && !$this->isFiltered()) {
            $this->doRecursiveAction(function ($node) use ($recursion) {
                $node->delete(true, $recursion, false);
            }, NodeInterface::DELETED_INCLUDE);
        }

        try {
            $this->_parent->getStorage()->forceDeleteCollection($this);
            $result = $this->db->storage->deleteOne(['_id' => $this->_id]);

            if ($this->isShared()) {
                $result = $this->db->storage->deleteMany(['reference' => $this->_id]);
            }

            $this->logger->info('force removed collection ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->_hook->run(
                'postDeleteCollection',
                [$this, true, $recursion, $recursion_first]
            );
        } catch (\Exception $e) {
            $this->logger->error('failed force remove collection ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }
}
