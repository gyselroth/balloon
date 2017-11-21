<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Resource;
use Balloon\Server\User;
use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use Sabre\DAV;
use Psr\Log\LoggerInterface;
use Balloon\Hook;
use Balloon\Filesystem\Acl;

class Collection extends AbstractNode implements DAV\ICollection, DAV\IQuota
{
    /**
     * Root folder.
     */
    const ROOT_FOLDER = '/';

    /**
     * Mime type.
     *
     * @var string
     */
    protected $mime = 'inode/directory';

    /**
     * Children.
     *
     * @var array
     */
    protected $children = [];

    /**
     * Share acl.
     *
     * @var array
     */
    protected $acl = [];

    /**
     * filter.
     *
     * @param array
     */
    protected $filter = [];

    /**
     * Initialize.
     *
     * @param array      $attributes
     * @param Filesystem $fs
     */
    public function __construct(array $attributes, Filesystem $fs, LoggerInterface $logger, Hook $hook, Acl $acl)
    {
        $this->_fs = $fs;
        $this->_server = $fs->getServer();
        $this->_db = $fs->getDatabase();
        $this->_user = $fs->getUser();
        $this->_logger = $logger;
        $this->_hook = $hook;
        $this->_acl = $acl;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        $this->raw_attributes = $attributes;
    }

    /**
     * Copy node with children.
     *
     * @param Collection $parent
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
     *
     * @return NodeInterface
     */
    public function copyTo(Collection $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): NodeInterface
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
        } else {
            $new_parent = $parent->addDirectory($name, [
                'created' => $this->created,
                'changed' => $this->changed,
                'deleted' => $this->deleted,
                'filter' => $this->filter,
                'app_attributes' => $this->app_attributes,
            ], NodeInterface::CONFLICT_NOACTION, true);
        }

        foreach ($this->getChildNodes(NodeInterface::DELETED_INCLUDE) as $child) {
            $child->copyTo($new_parent, $conflict, $recursion, false);
        }

        $this->_hook->run(
            'postCopyCollection',
            [$this, $parent, $new_parent, $conflict, $recursion, $recursion_first]
        );

        return $new_parent;
    }

    /**
     * Get share.
     *
     * @return array
     */
    public function getShareAcl(): array
    {
        return $this->acl;
        /*if (!$this->isShared()) {
            return false;
        }
        if (is_array($this->acl)) {
            $resource = new Resource($this->_user, $this->_logger, $this->_fs);
            $return = [];
            if (array_key_exists('user', $this->acl)) {
                foreach ((array) $this->acl['user'] as $user) {
                    $data = $resource->searchUser($user['user'], true);

                    if (empty($data)) {
                        continue;
                    }

                    $data['priv'] = $user['priv'];
                    $return[] = $data;
                }
            }
            if (array_key_exists('group', $this->acl)) {
                foreach ((array) $this->acl['group'] as $group) {
                    $data = $resource->searchGroup($group['group'], true);

                    if (empty($data)) {
                        continue;
                    }

                    $data['priv'] = $group['priv'];
                    $return[] = $data;
                }
            }

            return $return;
        }

        return false;*/
    }

    /**
     * Get Attribute.
     *
     * @param array $attributes
     *
     * @return array
     */
    public function getAttributes(array $attributes = []): array
    {
        if (empty($attributes)) {
            $attributes = [
                'id',
                'name',
                'meta',
                'mime',
                'reference',
                'deleted',
                'changed',
                'created',
                'share',
                'directory',
            ];
        }

        return parent::getAttributes($attributes);
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
     *
     * @param int   $deleted
     * @param array $filter
     * @param bool $skip_exception
     *
     * @return Generator
     */
    public function getChildNodes(int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = [], bool $skip_exception=true): Generator
    {
        if ($this->_user instanceof User) {
            $this->_user->findNewShares();
        }

        $search = [
            'parent' => $this->getRealId(),
        ];

        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $search['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $search['deleted'] = ['$type' => 9];
        }

        $search = array_merge($filter, $search);

        if ($this->shared) {
            $node = $this->_db->storage->find([
                '$and' => [
                    $search,
                    [
                        '$or' => [
                            ['shared' => $this->reference],
                            ['shared' => $this->shared],
                            ['shared' => $this->_id],
                        ],
                    ],
                ],
            ]);
        } else {
            if (null !== $this->_user) {
                $search['owner'] = $this->_user->getId();
            }

            $node = $this->_db->storage->find($search);
        }

        $list = [];
        foreach ($node as $child) {
            try {
                yield $this->_fs->initNode($child);
            } catch (\Exception $e) {
                if($skip_exception === false) {
                    throw $e;
                }

                $this->_logger->info('remove node from children list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        if (!empty($this->filter)) {
            foreach ($this->_fs->findNodesWithCustomFilterUser($deleted, $this->filter) as $node) {
                yield $node;
            }
        }
    }

    /**
     * Fetch children items of this collection (as array).
     *
     * Deleted:
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     *
     * @param int   $deleted
     * @param array $filter
     *
     * @return Generator
     */
    public function getChildren(int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): array
    {
        return iterator_to_array($this->getChildNodes($deleted, $filter));
    }

    /**
     * Is custom filter node.
     *
     * @return bool
     */
    public function isCustomFilter(): bool
    {
        return !empty($this->filter);
    }

    /**
     * Get number of children.
     *
     * @return int
     */
    public function getSize(): int
    {
        if ($this->isDeleted()) {
            return count(iterator_to_array($this->getChildNodes(NodeInterface::DELETED_INCLUDE)));
        }

        return count(iterator_to_array($this->getChildNodes()));
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
     *
     * @return array
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
     *
     * @param string $node
     * @param int                    $deleted
     * @param array                  $filter
     *
     * @return NodeInterface
     */
    public function getChild($name, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): NodeInterface
    {
        $search = [
            'name'   => new Regex('^'.preg_quote($name).'$', 'i'),
            'parent' => $this->getRealId(),
        ];

        switch ($deleted) {
            case NodeInterface::DELETED_EXCLUDE:
                $search['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $search['deleted'] = ['$type' => '9'];

                break;
        }

        $search = array_merge($filter, $search);

        if ($this->shared) {
            $node = $this->_db->storage->findOne([
                '$and' => [
                    $search,
                    [
                        '$or' => [
                            ['shared' => $this->reference],
                            ['shared' => $this->shared],
                            ['shared' => $this->_id],
                        ],
                    ],
                ],
            ]);
        } else {
            $search['owner'] = $this->_user->getId();
            $node = $this->_db->storage->findOne($search);
        }

        if (null === $node) {
            throw new Exception\NotFound(
                'node called '.$name.' does not exists here',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $this->_logger->debug('loaded node ['.$node['_id'].' from parent node ['.$this->getRealId().']', [
            'category' => get_class($this),
        ]);

        return $this->_fs->initNode($node);
    }

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     *
     * @param bool   $force
     * @param string $recursion       Identifier to identify a recursive action
     * @param bool   $recursion_first
     *
     * @return bool
     */
    public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->_acl->isAllowed($this, 'w') && !$this->isReference()) {
            throw new Exception\Forbidden(
                'not allowed to delete node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_DELETE
            );
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

        if ($this->readonly && null !== $this->_user) {
            throw new Exception\Conflict(
                'node is marked as readonly, it is not possible to delete it',
                Exception\Conflict::READONLY
            );
        }

        if (true === $force) {
            return $this->_forceDelete($recursion, $recursion_first);
        }

        $this->deleted = new UTCDateTime();

        if (!$this->isReference()) {
            $this->doRecursiveAction('delete', [
                'force' => false,
                'recursion' => $recursion,
                'recursion_first' => false,
            ], NodeInterface::DELETED_EXCLUDE, false);
        }

        if (null !== $this->_id) {
            $result = $this->save([
                'deleted',
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
     * @param array  $filter
     *
     * @return bool
     */
    public function childExists($name, $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): bool
    {
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
     *
     * @param array $acl
     *
     * @return bool
     */
    public function share(array $acl): bool
    {
        if ($this->isShareMember()) {
            throw new Exception('a sub node of a share can not be shared');
        }

        if (!$this->_acl->isAllowed($this, 'm')) {
            throw new Exception\Forbidden(
                'not allowed to share node',
                Exception\Forbidden::NOT_ALLOWED_TO_SHARE
            );
        }

        $this->_acl->validateAcl($acl);

        $action = [
            '$set' => [
                'shared' => $this->getRealId(),
            ],
        ];

        $toset = $this->getChildrenRecursive($this->getRealId(), $shares, $files);

        if (!empty($shares)) {
            throw new Exception('child folder contains a shared folder');
        }

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

        if($this->getRealId() === $this->_id) {
            $this->acl = $acl;
            $this->shared = true;
            $this->save(['acl', 'shared']);
        } else {
            $this->_db->storage->updateOne([
                '_id' => $this->getRealId(),
            ], [
                '$set' => [
                    'acl' => $acl
                ]
            ]);
        }

        if (!is_array($files)) {
            return true;
        }

        foreach ($files as $node) {
            if (null === $node['storage']) {
                continue;
            }

            $share_ref = [
                'id' => $node['id'],
                'share' => $this->_id,
            ];

            $this->_db->{'fs.files'}->updateOne(
                $node['storage']['attributes'],
                [
                    '$addToSet' => [
                        'metadata.share_ref' => $share_ref,
                    ],
                ]
            );
        }

        return true;
    }

    /**
     * Unshare collection.
     *
     * @return bool
     */
    public function unshare(): bool
    {
        if (!$this->_acl->isAllowed($this, 'm')) {
            throw new Exception\Forbidden(
                'not allowed to share node',
                Exception\Forbidden::NOT_ALLOWED_TO_SHARE
            );
        }

        if (true !== $this->shared) {
            throw new Exception\Conflict(
                'Can not unshare a none shared collection',
                Exception\Conflict::NOT_SHARED
            );
        }

        $this->shared = false;
        $this->acl = null;
        $action = [
            '$unset' => [
                'shared' => $this->_id,
            ],
            '$set' => [
                'owner' => $this->_user->getId(),
            ],
        ];

        $toset = $this->getChildrenRecursive($this->getRealId(), $shares, $files);

        $this->_db->storage->updateMany([
            '_id' => [
                '$in' => $toset,
            ],
        ], $action);

        $result = $this->save(['shared'], ['acl']);

        if (!is_array($files)) {
            return true;
        }

        foreach ($files as $node) {
            if (null === $node['storage']) {
                continue;
            }

            $share_ref = [
                'id' => $node['id'],
                'share' => $this->_id,
            ];

            $this->_db->{'fs.files'}->updateOne(
                $node['storage']['attributes'],
                [
                '$pull' => [
                    'metadata.share_ref' => $share_ref,
                    ],
                ]
            );
        }

        return true;
    }

    /**
     * Get children.
     *
     * @param ObjectId $id
     * @param array    $shares
     * @param arary    $files
     *
     * @return array
     */
    public function getChildrenRecursive(?ObjectId $id = null, ?array &$shares = [], ?array &$files = []): array
    {
        $list = [];
        $result = $this->_db->storage->find([
            'parent' => $id,
        ], [
            '_id' => 1,
            'directory' => 1,
            'reference' => 1,
            'shared' => 1,
            'storage' => 1,
        ]);

        foreach ($result as $node) {
            $list[] = $node['_id'];

            if (false === $node['directory']) {
                $files[] = [
                    'id' => $node['_id'],
                    'storage' => isset($node['storage']) ? $node['storage'] : null,
                ];
            } else {
                if (isset($node['reference']) || isset($node['shared']) && true === $node['shared']) {
                    $shares[] = $node['_id'];
                }

                if (true === $node['directory'] && !isset($node['reference'])) {
                    $list = array_merge($list, $this->getChildrenRecursive($node['_id'], $shares, $files));
                }
            }
        }

        return $list;
    }

    /**
     * Create new directory.
     *
     * @param string $name
     * @param arracy $attributes
     * @param int    $conflict
     * @param bool   $clone
     *
     * @return Collection
     */
    public function addDirectory($name, array $attributes = [], int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): Collection
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new Exception\Forbidden(
                'not allowed to create new node here',
                Exception\Forbidden::NOT_ALLOWED_TO_CREATE
            );
        }

        $this->_hook->run('preCreateCollection', [$this, &$name, &$attributes, &$clone]);

        if ($this->readonly) {
            throw new Exception\Conflict(
                'node is set as readonly, it is not possible to add new sub nodes',
                Exception\Conflict::READONLY
            );
        }

        $name = $this->checkName($name);

        if ($this->childExists($name)) {
            if (NodeInterface::CONFLICT_NOACTION === $conflict) {
                throw new Exception\Conflict(
                    'a node called '.$name.' does already exists in this collection',
                    Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
                );
            }
            if (NodeInterface::CONFLICT_RENAME === $conflict) {
                $name = $this->getDuplicateName($name);
            }
        }

        if ($this->isDeleted()) {
            throw new Exception\Conflict(
                'could not add node '.$name.' into a deleted parent collection',
                Exception\Conflict::DELETED_PARENT
            );
        }

        try {
            $meta = [
                'name' => $name,
                'deleted' => false,
                'parent' => $this->getRealId(),
                'directory' => true,
                'meta' => [],
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
                'shared' => (true === $this->shared ? $this->getRealId() : $this->shared),
            ];

            if (null !== $this->_user) {
                $meta['owner'] = $this->_user->getId();
            }

            $save = array_merge($meta, $attributes);

            $result = $this->_db->storage->insertOne($save);
            $save['_id'] = $result->getInsertedId();

            $this->_logger->info('added new collection ['.$save['_id'].'] under parent ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $new = $this->_fs->initNode($save);
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
     *
     * @param string           $name
     * @param ressource|string $data
     * @param array            $attributes
     * @param int              $conflict
     * @param bool             $clone
     *
     * @return File
     */
    public function addFile($name, $data = null, array $attributes = [], int $conflict = NodeInterface::CONFLICT_NOACTION, bool $clone = false): File
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new Exception\Forbidden(
                'not allowed to create new node here',
                Exception\Forbidden::NOT_ALLOWED_TO_CREATE
            );
        }

        $this->_hook->run('preCreateFile', [$this, &$name, &$attributes, &$clone]);

        if ($this->readonly) {
            throw new Exception\Conflict(
                'node is set as readonly, it is not possible to add new sub nodes',
                Exception\Conflict::READONLY
            );
        }

        $name = $this->checkName($name);

        if ($this->childExists($name)) {
            if (NodeInterface::CONFLICT_NOACTION === $conflict) {
                throw new Exception\Conflict(
                    'a node called '.$name.' does already exists in this collection',
                    Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
                );
            }
            if (NodeInterface::CONFLICT_RENAME === $conflict) {
                $name = $this->getDuplicateName($name);
            }
        }

        if ($this->isDeleted()) {
            throw new Exception\Conflict(
                'could not add node '.$name.' into a deleted parent collection',
                Exception\Conflict::DELETED_PARENT
            );
        }

        try {
            $meta = [
                'name' => $name,
                'deleted' => false,
                'parent' => $this->getRealId(),
                'directory' => false,
                'hash' => null,
                'meta' => [],
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
                'history' => [],
                'version' => 0,
                'shared' => (true === $this->shared ? $this->getRealId() : $this->shared),
                'storage_adapter' => $this->storage_adapter,
            ];

            if (null !== $this->_user) {
                $meta['owner'] = $this->_user->getId();
            }

            $save = array_merge($meta, $attributes);

            $result = $this->_db->storage->insertOne($save);
            $save['_id'] = $result->getInsertedId();

            $this->_logger->info('added new file ['.$save['_id'].'] under parent ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $file = $this->_fs->initNode($save);

            try {
                $file->put($data, true, $attributes);
            } catch (Exception\InsufficientStorage $e) {
                $this->_logger->warning('failed add new file under parent ['.$this->_id.'], cause user quota is full', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                $this->_db->storage->deleteOne([
                    '_id' => $save['_id'],
                ]);

                throw $e;
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
     *
     * @return File
     */
    public function createFile($name, $data = null): String
    {
        return $this->addFile($name, $data)->getETag();
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
     *
     * @param string $method
     * @param array  $params
     * @param int    $deleted
     * @param bool $ignore_exception
     *
     * @return bool
     */
    protected function doRecursiveAction(string $method, array $params = [], int $deleted = NodeInterface::DELETED_EXCLUDE, bool $ignore_exception=true): bool
    {
        if (!is_callable([$this, $method])) {
            throw new Exception("method $method is not callable in ".__CLASS__);
        }

        $children = $this->getChildNodes($deleted, [], $ignore_exception);

        foreach ($children as $child) {
            call_user_func_array([$child, $method], $params);
        }

        return true;
    }

    /**
     * Completely remove node.
     *
     * @param string $recursion       Identifier to identify a recursive action
     * @param bool   $recursion_first
     *
     * @return bool
     */
    protected function _forceDelete(?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->isReference()) {
            $this->doRecursiveAction('delete', [
                'force' => true,
                'recursion' => $recursion,
                'recursion_first' => false,
            ], NodeInterface::DELETED_INCLUDE, false);
        }

        try {
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
