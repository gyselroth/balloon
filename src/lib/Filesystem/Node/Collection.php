<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Balloon\Hook;
use Balloon\Server\User;
use Balloon\Session\Factory as SessionFactory;
use Balloon\Session\SessionInterface;
use Generator;
use MimeType\MimeType;
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use function MongoDB\BSON\toPHP;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use Sabre\DAV\IQuota;

class Collection extends AbstractNode implements IQuota
{
    /**
     * Root folder.
     */
    const ROOT_FOLDER = '/';

    /**
     * Share acl.
     *
     * @var array
     */
    protected $acl = [];

    /**
     * Share name.
     *
     * @var string
     */
    protected $share_name;

    /**
     * filter.
     *
     * @var string
     */
    protected $filter;

    /**
     * Storage.
     *
     * @var StorageAdapterInterface
     */
    protected $_storage;

    /**
     * Initialize.
     */
    public function __construct(array $attributes, Filesystem $fs, LoggerInterface $logger, Hook $hook, Acl $acl, ?Collection $parent, StorageAdapterInterface $storage, SessionFactory $session_factory)
    {
        $this->_fs = $fs;
        $this->_server = $fs->getServer();
        $this->_db = $fs->getDatabase();
        $this->_user = $fs->getUser();
        $this->_logger = $logger;
        $this->_hook = $hook;
        $this->_acl = $acl;
        $this->_storage = $storage;
        $this->_parent = $parent;
        $this->_session_factory = $session_factory;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        $this->mime = 'inode/directory';
        $this->raw_attributes = $attributes;
    }

    /**
     * Set storage adapter.
     */
    public function setStorage(StorageAdapterInterface $adapter): self
    {
        $this->_storage = $adapter;

        return $this;
    }

    /**
     * Get storage adapter.
     */
    public function getStorage(): StorageAdapterInterface
    {
        return $this->_storage;
    }

    /**
     * Copy node with children.
     */
    public function copyTo(self $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = NodeInterface::DELETED_EXCLUDE): NodeInterface
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
            throw new Exception\Conflict('can not copy node into itself', Exception\Conflict::CANT_COPY_INTO_ITSELF);
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

        foreach ($this->getChildren($deleted) as $child) {
            $child->copyTo($new_parent, $conflict, $recursion, false, $deleted);
        }

        $this->_hook->run(
            'postCopyCollection',
            [$this, $parent, $new_parent, $conflict, $recursion, $recursion_first]
        );

        return $new_parent;
    }

    /**
     * Is mount.
     */
    public function isMounted(): bool
    {
        return count($this->mount) > 0;
    }

    /**
     * Get Share name.
     */
    public function getShareName(): string
    {
        if ($this->isShare()) {
            return $this->share_name;
        }

        return $this->_fs->findRawNode($this->getShareId())['share_name'];
    }

    /**
     * Get Attributes.
     */
    public function getAttributes(): array
    {
        return [
            '_id' => $this->_id,
            'name' => $this->name,
            'shared' => $this->shared,
            'share_name' => $this->share_name,
            'acl' => $this->acl,
            'lock' => $this->lock,
            'directory' => true,
            'reference' => $this->reference,
            'parent' => $this->parent,
            'app' => $this->app,
            'owner' => $this->owner,
            'meta' => $this->meta,
            'mime' => $this->mime,
            'filter' => $this->filter,
            'deleted' => $this->deleted,
            'changed' => $this->changed,
            'created' => $this->created,
            'destroy' => $this->destroy,
            'readonly' => $this->readonly,
            'mount' => $this->mount,
            'storage_reference' => $this->storage_reference,
            'storage' => $this->storage,
        ];
    }

    /**
     * Set collection filter.
     */
    public function setFilter(?array $filter = null): bool
    {
        $this->filter = json_encode($filter, JSON_THROW_ON_ERROR);

        return $this->save('filter');
    }

    /**
     * Get collection.
     */
    public function get(): void
    {
        $this->getZip();
    }

    /**
     * Fetch children items of this collection.
     *
     * Deleted:
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     */
    public function getChildren(int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = [], ?int $offset = null, ?int $limit = null, bool $recursive = false): Generator
    {
        $filter = $this->getChildrenFilter($deleted, $filter);

        if ($recursive === false) {
            return $this->_fs->findNodesByFilterRecursiveChildren($filter, $deleted, $offset, $limit, $this);
        }

        unset($filter['parent']);

        return $this->_fs->findNodesByFilterRecursive($this, $filter, $offset, $limit);
    }

    /**
     * Is custom filter node.
     */
    public function isFiltered(): bool
    {
        return !empty($this->filter);
    }

    /**
     * Get number of children.
     */
    public function getSize(): int
    {
        if ($this->isFiltered() || $this->_acl->getAclPrivilege($this) === Acl::PRIVILEGE_WRITEPLUS) {
            return count(iterator_to_array($this->getChildren()));
        }

        return $this->size;
    }

    /**
     * Get real id (reference).
     *
     * @return ObjectId
     */
    public function getRealId(): ?ObjectId
    {
        if (true === $this->shared && $this->isReference()) {
            return $this->reference;
        }

        return $this->_id;
    }

    /**
     * Get user quota information.
     */
    public function getQuotaInfo(): array
    {
        $quota = $this->_user->getQuotaUsage();

        return [
            $quota['used'],
            $quota['available'],
        ];
    }

    /**
     * Fetch children items of this collection.
     */
    public function getChild($name, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): NodeInterface
    {
        $name = $this->checkName($name);
        $filter = $this->getChildrenFilter($deleted, $filter);
        $filter['name'] = new Regex('^'.preg_quote($name).'$', 'i');

        return $this->_fs->findOne($filter, $deleted, $this);
    }

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     */
    public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->isReference() && !$this->_acl->isAllowed($this, 'w')) {
            throw new ForbiddenException('not allowed to delete node '.$this->name, ForbiddenException::NOT_ALLOWED_TO_DELETE);
        }

        if (null === $recursion) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->_hook->run(
            'preDeleteCollection',
            [$this, &$force, &$recursion, &$recursion_first]
        );

        if (true === $force) {
            return $this->_forceDelete($recursion, $recursion_first);
        }

        $this->deleted = new UTCDateTime();
        $this->storage = $this->_parent->getStorage()->deleteCollection($this);

        if (!$this->isReference() && !$this->isMounted() && !$this->isFiltered()) {
            $this->doRecursiveAction(function ($node) use ($recursion) {
                $node->delete(false, $recursion, false);
            }, NodeInterface::DELETED_EXCLUDE);
        }

        if (null !== $this->_id) {
            $result = $this->save([
                'deleted', 'storage',
            ], [], $recursion, false);
        } else {
            $result = true;
        }

        $this->_hook->run(
            'postDeleteCollection',
            [$this, $force, $recursion, $recursion_first]
        );

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
    public function childExists($name, $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): bool
    {
        $name = $this->checkName($name);

        $find = [
            'parent' => $this->getRealId(),
            'name' => new Regex('^'.preg_quote($name).'$', 'i'),
        ];

        if (null !== $this->_user) {
            $find['owner'] = $this->_user->getId();
        }

        switch ($deleted) {
            case NodeInterface::DELETED_EXCLUDE:
                $find['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $find['deleted'] = ['$type' => 9];

                break;
        }

        $find = array_merge($filter, $find);

        if ($this->isSpecial()) {
            unset($find['owner']);
        }

        $node = $this->_db->storage->findOne($find);

        return (bool) $node;
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
        $this->_acl->validateAcl($this->_server, $acl);

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
            throw new Exception\Conflict('folder contains a shared folder', Exception\Conflict::NODE_CONTAINS_SHARED_NODE);
        }

        $toset = $this->_fs->findNodesByFilterRecursiveToArray($this);
        $this->_db->storage->updateMany([
            '_id' => [
                '$in' => $toset,
            ],
        ], $action);

        $this->_db->delta->updateMany([
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
            $this->_db->storage->updateOne([
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
    public function unshare(): Collection
    {
        if (!$this->_acl->isAllowed($this, 'm')) {
            throw new ForbiddenException('not allowed to share node', ForbiddenException::NOT_ALLOWED_TO_MANAGE);
        }

        if (true !== $this->shared) {
            throw new Exception\Conflict('Can not unshare a none shared collection', Exception\Conflict::NOT_SHARED);
        }

        $real = $this->getRealId();

        $action = [
            '$set' => [
                'owner' => $this->_fs->findRawNode($real)['owner'],
                'shared' => false,
            ],
        ];

        $this->shared = false;
        $this->share_name = null;
        $this->acl = [];

        $toset = $this->_fs->findNodesByFilterRecursiveToArray($this);
        $this->_db->storage->updateMany([
            '_id' => [
                '$in' => $toset,
            ],
        ], $action);

        if ($real === $this->_id) {
            $result = $this->save(['shared'], ['acl', 'share_name']);
        } else {
            $this->_db->storage->updateOne([
                '_id' => $real,
            ], [
                '$set' => [
                    'share_name' => null,
                    'acl' => [],
                    'shared' => false,
                ],
            ]);

            $this->delete();
        }

        return $this;
    }

    /**
     * Create new directory.
     */
    public function addDirectory($name, array $attributes = [], int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): self
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new ForbiddenException('not allowed to create new node here', ForbiddenException::NOT_ALLOWED_TO_CREATE);
        }

        $this->_hook->run('preCreateCollection', [$this, &$name, &$attributes, &$clone]);
        $name = $this->validateInsert($name, $conflict, Collection::class);
        $id = new ObjectId();

        if (isset($attributes['lock'])) {
            $attributes['lock'] = $this->prepareLock($attributes['lock']);
        }

        try {
            $meta = [
                '_id' => $id,
                'pointer' => $id,
                'name' => $name,
                'deleted' => false,
                'parent' => $this->getRealId(),
                'directory' => true,
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
                'shared' => (true === $this->shared ? $this->getRealId() : $this->shared),
                'storage' => $this->_storage->createCollection($this, $name),
                'storage_reference' => $this->getMount(),
            ];

            if (null !== $this->_user) {
                $meta['owner'] = $this->_user->getId();
            }

            $save = array_merge($meta, $attributes);

            if (isset($save['filter'])) {
                $this->validateFilter($save['filter']);
            }

            $result = $this->_db->storage->insertOne($save);

            $this->_logger->info('added new collection ['.$save['_id'].'] under parent ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->changed = $save['changed'];
            $this->save('changed');

            $new = $this->_fs->initNode($save, $this);
            $this->_hook->run('postCreateCollection', [$this, $new, $clone]);

            return $new;
        } catch (\Exception $e) {
            $this->_logger->error('failed create new collection under parent ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Create new file as a child from this collection.
     */
    public function addFile($name, ?SessionInterface $session = null, array $attributes = [], int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): File
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new ForbiddenException('not allowed to create new node here', ForbiddenException::NOT_ALLOWED_TO_CREATE);
        }

        $this->_hook->run('preCreateFile', [$this, &$name, &$attributes, &$clone]);
        $name = $this->validateInsert($name, $conflict, File::class);
        $id = new ObjectId();

        if (isset($attributes['lock'])) {
            $attributes['lock'] = $this->prepareLock($attributes['lock']);
        }

        try {
            $meta = [
                '_id' => $id,
                'pointer' => $id,
                'name' => $name,
                'deleted' => false,
                'parent' => $this->getRealId(),
                'directory' => false,
                'hash' => null,
                'mime' => MimeType::getType($name),
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
                'version' => 0,
                'shared' => (true === $this->shared ? $this->getRealId() : $this->shared),
                'storage_reference' => $this->getMount(),
            ];

            if (null !== $this->_user) {
                $meta['owner'] = $this->_user->getId();
            }

            $save = array_merge($meta, $attributes);
            $result = $this->_db->storage->insertOne($save);

            $this->_logger->info('added new file ['.$save['_id'].'] under parent ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->changed = $save['changed'];
            $this->save('changed');

            $file = $this->_fs->initNode($save, $this);

            if ($session !== null) {
                $file->setContent($session, $attributes);
            }

            $this->_hook->run('postCreateFile', [$this, $file, $clone]);

            return $file;
        } catch (\Exception $e) {
            $this->_logger->error('failed add new file under parent ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Create new file wrapper
     * (Sabe\DAV compatible method, elsewhere use addFile().
     *
     * Sabre\DAV requires that createFile() returns the ETag instead the newly created file instance
     *
     * @param string $name
     * @param string $data
     */
    public function createFile($name, $data = null): string
    {
        $session = $this->_session_factory->add($this->_user, $this->getParent(), $data);

        if ($this->childExists($name, NodeInterface::DELETED_INCLUDE, ['directory' => false])) {
            $file = $this->getChild($name, NodeInterface::DELETED_INCLUDE, ['directory' => false]);
            $file->setContent($session);
        } else {
            $file = $this->addFile($name, $session);
        }

        return $file->getETag();
    }

    /**
     * Create new directory wrapper
     * (Sabe\DAV compatible method, elsewhere use addDirectory().
     *
     * Sabre\DAV requires that createDirectory() returns void
     *
     * @param string $name
     */
    public function createDirectory($name): void
    {
        $this->addDirectory($name);
    }

    /**
     * Do recursive Action.
     */
    public function doRecursiveAction(callable $callable, int $deleted = NodeInterface::DELETED_EXCLUDE): bool
    {
        $children = $this->getChildren($deleted, []);

        foreach ($children as $child) {
            $callable($child);
        }

        return true;
    }

    /**
     * Validate insert.
     */
    public function validateInsert(string $name, int $conflict = NodeInterface::CONFLICT_NOACTION, string $type = Collection::class): string
    {
        if ($this->readonly) {
            throw new Exception\Conflict('node is set as readonly, it is not possible to add new sub nodes', Exception\Conflict::READONLY);
        }

        if ($this->isFiltered()) {
            throw new Exception\Conflict('could not add node '.$name.' into a filtered parent collection', Exception\Conflict::DYNAMIC_PARENT);
        }

        if ($this->isDeleted()) {
            throw new Exception\Conflict('could not add node '.$name.' into a deleted parent collection', Exception\Conflict::DELETED_PARENT);
        }

        $name = $this->checkName($name);

        if ($this->childExists($name)) {
            if (NodeInterface::CONFLICT_NOACTION === $conflict) {
                throw new Exception\Conflict('a node called '.$name.' does already exists in this collection', Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS);
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

        $this->_db->storage->findOne($filter);

        return true;
    }

    /**
     * Validate acl.
     */
    protected function validateAcl(array $acl): bool
    {
        if (!$this->_acl->isAllowed($this, 'm')) {
            throw new ForbiddenException('not allowed to set acl', ForbiddenException::NOT_ALLOWED_TO_MANAGE);
        }

        if (!$this->isSpecial()) {
            throw new Exception\Conflict('node acl may only be set on share member nodes', Exception\Conflict::NOT_SHARED);
        }

        $this->_acl->validateAcl($this->_server, $acl);

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
    protected function getChildrenFilter(int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): array
    {
        $search = $filter;
        $search['parent'] = $this->getRealId();

        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $search['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $search['deleted'] = ['$type' => 9];
        }

        if ($this->shared) {
            $or = [
                [
                    'acl' => ['$exists' => false],
                ],
            ];

            if (null !== $this->_user) {
                $or[] = [
                    'acl.id' => (string)$this->_user->getId(),
                    'acl.privilege' => ['$in' => ['m', 'rw', 'r', 'w', 'w+']]
                ];
            }

            $search = [
                '$and' => [
                    $search,
                    [
                        '$or' => [
                            ['shared' => $this->reference],
                            ['shared' => $this->shared],
                            ['shared' => $this->_id],
                        ],
                    ],
                    [
                        '$or' => $or,
                    ],
                ],
            ];
        } elseif (null !== $this->_user) {
            $search['owner'] = $this->_user->getId();
        }

        if ($this->filter !== null && $this->_user !== null) {
            $stored = toPHP(fromJSON($this->filter), [
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
            $result = $this->_db->storage->deleteOne(['_id' => $this->_id]);

            if ($this->isShared()) {
                $result = $this->_db->storage->deleteMany(['reference' => $this->_id]);
            }

            $this->_logger->info('force removed collection ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->_hook->run(
                'postDeleteCollection',
                [$this, true, $recursion, $recursion_first]
            );
        } catch (\Exception $e) {
            $this->_logger->error('failed force remove collection ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }
}
