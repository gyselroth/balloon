<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Exception;
use Balloon\Hook;
use Balloon\Server;
use Balloon\Server\User;
use MimeType\MimeType;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Normalizer;
use Psr\Log\LoggerInterface;
use ZipStream\ZipStream;

abstract class AbstractNode implements NodeInterface
{
    /**
     * name max lenght.
     */
    const MAX_NAME_LENGTH = 255;

    /**
     * Unique id.
     *
     * @var ObjectId
     */
    protected $_id;

    /**
     * Node name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Owner.
     *
     * @var ObjectId
     */
    protected $owner;

    /**
     * Mime.
     *
     * @var string
     */
    protected $mime;

    /**
     * Meta attributes.
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Parent collection.
     *
     * @var ObjectId
     */
    protected $parent;

    /**
     * Is file deleted.
     *
     * @var bool|UTCDateTime
     */
    protected $deleted = false;

    /**
     * Is shared?
     *
     * @var bool
     */
    protected $shared = false;

    /**
     * Destory at a certain time.
     *
     * @var UTCDateTime
     */
    protected $destroy;

    /**
     * Changed timestamp.
     *
     * @var UTCDateTime
     */
    protected $changed;

    /**
     * Created timestamp.
     *
     * @var UTCDateTime
     */
    protected $created;

    /**
     * Point to antother node (Means this node is reference to $reference).
     *
     * @var ObjectId
     */
    protected $reference;

    /**
     * Raw attributes before any processing or modifications.
     *
     * @var array
     */
    protected $raw_attributes;

    /**
     * Readonly flag.
     *
     * @var bool
     */
    protected $readonly = false;

    /**
     * App attributes.
     *
     * @var array
     */
    protected $app = [];

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $_fs;

    /**
     * Database.
     *
     * @var Database
     */
    protected $_db;

    /**
     * User.
     *
     * @var User
     */
    protected $_user;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * Server.
     *
     * @var Server
     */
    protected $_server;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $_hook;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $_acl;

    /**
     * Mount.
     *
     * @var ObjectId
     */
    protected $storage_reference;

    /**
     * Storage attributes.
     *
     * @var array
     */
    protected $storage;

    /**
     * File size for files, number of children for directories.
     *
     * @var int
     */
    protected $size = 0;

    /**
     * Acl.
     *
     * @var array
     */
    protected $acl = [];

    /**
     * Mount.
     *
     * @var array
     */
    protected $mount = [];

    /**
     * Lock.
     *
     * @var array
     */
    protected $lock;

    /**
     * Parent collection.
     *
     * @var Collection
     */
    protected $_parent;

    /**
     * Session factory.
     *
     * @var SessionFactory
     */
    protected $_session_factory;

    /**
     * Convert to filename.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Get owner.
     */
    public function getOwner(): ObjectId
    {
        return $this->owner;
    }

    /**
     * Set filesystem.
     */
    public function setFilesystem(Filesystem $fs): NodeInterface
    {
        $this->_fs = $fs;
        $this->_user = $fs->getUser();

        return $this;
    }

    /**
     * Get filesystem.
     */
    public function getFilesystem(): Filesystem
    {
        return $this->_fs;
    }

    /**
     * Check if $node is a sub node of any parent nodes of this node.
     */
    public function isSubNode(NodeInterface $node): bool
    {
        if ($node->getId() == $this->_id) {
            return true;
        }

        foreach ($node->getParents() as $node) {
            if ($node->getId() == $this->_id) {
                return true;
            }
        }

        if ($this->isRoot()) {
            return true;
        }

        return false;
    }

    /**
     * Move node.
     */
    public function setParent(Collection $parent, int $conflict = NodeInterface::CONFLICT_NOACTION): NodeInterface
    {
        if ($this->parent == $parent->getId()) {
            throw new Exception\Conflict('source node '.$this->name.' is already in the requested parent folder', Exception\Conflict::ALREADY_THERE);
        }
        if ($this->isSubNode($parent)) {
            throw new Exception\Conflict('node called '.$this->name.' can not be moved into itself', Exception\Conflict::CANT_BE_CHILD_OF_ITSELF);
        }
        if (!$this->_acl->isAllowed($this, 'w') && !$this->isReference()) {
            throw new ForbiddenException('not allowed to move node '.$this->name, ForbiddenException::NOT_ALLOWED_TO_MOVE);
        }

        $new_name = $parent->validateInsert($this->name, $conflict, get_class($this));

        if ($this->isShared() && $this instanceof Collection && $parent->isShared()) {
            throw new Exception\Conflict('a shared folder can not be a child of a shared folder', Exception\Conflict::SHARED_NODE_CANT_BE_CHILD_OF_SHARE);
        }

        if (NodeInterface::CONFLICT_RENAME === $conflict && $new_name !== $this->name) {
            $this->setName($new_name);
            $this->raw_attributes['name'] = $this->name;
        }

        if ($this instanceof Collection) {
            $query = [
                '$or' => [
                    ['reference' => ['exists' => true]],
                    ['shared' => true],
                ],
            ];

            if ($parent->isShared() && iterator_count($this->_fs->findNodesByFilterRecursive($this, $query, 0, 1)) !== 0) {
                throw new Exception\Conflict('folder contains a shared folder', Exception\Conflict::NODE_CONTAINS_SHARED_NODE);
            }
        }

        if ($this->isShared() && $parent->isSpecial()) {
            throw new Exception\Conflict('a shared folder can not be an indirect child of a shared folder', Exception\Conflict::SHARED_NODE_CANT_BE_INDIRECT_CHILD_OF_SHARE);
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
     * Lock file.
     */
    public function lock(string $identifier, ?int $ttl = 1800): NodeInterface
    {
        if ($this->isLocked()) {
            if ($identifier !== $this->lock['id']) {
                throw new Exception\LockIdMissmatch('the unlock id must match the current lock id');
            }
        }

        $this->lock = $this->prepareLock($identifier, $ttl ?? 1800);
        $this->save(['lock']);

        return $this;
    }

    /**
     * Get lock.
     */
    public function getLock(): array
    {
        if (!$this->isLocked()) {
            throw new Exception\NotLocked('node is not locked');
        }

        return $this->lock;
    }

    /**
     * Is locked?
     */
    public function isLocked(): bool
    {
        if ($this->lock === null) {
            return false;
        }
        if ($this->lock['expire'] <= new UTCDateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Unlock.
     */
    public function unlock(?string $identifier = null): NodeInterface
    {
        if (!$this->isLocked()) {
            throw new Exception\NotLocked('node is not locked');
        }

        if ($this->lock['owner'] != $this->_user->getId()) {
            throw new Exception\Forbidden('node is locked by another user');
        }

        if ($identifier !== null && $this->lock['id'] !== $identifier) {
            throw new Exception\LockIdMissmatch('the unlock id must match the current lock id');
        }

        $this->lock = null;
        $this->save(['lock']);

        return $this;
    }

    /**
     * Get ACL.
     */
    public function getAcl(): array
    {
        if ($this->isReference()) {
            $acl = $this->_fs->findRawNode($this->getShareId())['acl'];
        } else {
            $acl = $this->acl;
        }

        return $this->_acl->resolveAclTable($this->_server, $acl);
    }

    /**
     * Get share id.
     */
    public function getShareId(bool $reference = false): ?ObjectId
    {
        if ($this->isReference() && true === $reference) {
            return $this->_id;
        }
        if ($this->isShareMember() && true === $reference) {
            return $this->shared;
        }
        if ($this->isShared() && $this->isReference()) {
            return $this->reference;
        }
        if ($this->isShared()) {
            return $this->_id;
        }
        if ($this->isShareMember()) {
            return $this->shared;
        }

        return null;
    }

    /**
     * Get reference.
     */
    public function getReference(): ?ObjectId
    {
        return $this->reference;
    }

    /**
     * Get share node.
     */
    public function getShareNode(): ?Collection
    {
        if ($this->isShare()) {
            return $this;
        }

        if ($this->isSpecial()) {
            return $this->_fs->findNodeById($this->getShareId(true));
        }

        return null;
    }

    /**
     * Is node marked as readonly?
     */
    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * May write.
     */
    public function mayWrite(): bool
    {
        return Acl::PRIVILEGES_WEIGHT[$this->_acl->getAclPrivilege($this)] > Acl::PRIVILEGE_READ;
    }

    /**
     * Request is from node owner?
     */
    public function isOwnerRequest(): bool
    {
        return null !== $this->_user && $this->owner == $this->_user->getId();
    }

    /**
     * Check if node is kind of special.
     */
    public function isSpecial(): bool
    {
        if ($this->isShared()) {
            return true;
        }
        if ($this->isReference()) {
            return true;
        }
        if ($this->isShareMember()) {
            return true;
        }

        return false;
    }

    /**
     * Check if node is a sub node of a share.
     */
    public function isShareMember(): bool
    {
        return $this->shared instanceof ObjectId && !$this->isReference();
    }

    /**
     * Check if node is a sub node of an external storage mount.
     */
    public function isMountMember(): bool
    {
        return $this->storage_reference instanceof ObjectId;
    }

    /**
     * Is share.
     */
    public function isShare(): bool
    {
        return true === $this->shared && !$this->isReference();
    }

    /**
     * Is share (Reference or master share).
     */
    public function isShared(): bool
    {
        if (true === $this->shared) {
            return true;
        }

        return false;
    }

    /**
     * Set the name.
     */
    public function setName($name): bool
    {
        $name = $this->checkName($name);

        try {
            $child = $this->getParent()->getChild($name);
            if ($child->getId() != $this->_id) {
                throw new Exception\Conflict('a node called '.$name.' does already exists in this collection', Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS);
            }
        } catch (Exception\NotFound $e) {
            //child does not exists, we can safely rename
        }

        $this->storage = $this->_parent->getStorage()->rename($this, $name);
        $this->name = $name;

        if ($this instanceof File) {
            $this->mime = MimeType::getType($this->name);
        }

        return $this->save(['name', 'storage', 'mime']);
    }

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
     * Get the name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get mount node.
     */
    public function getMount(): ?ObjectId
    {
        return count($this->mount) > 0 ? $this->_id : $this->storage_reference;
    }

    /**
     * Undelete.
     */
    public function undelete(int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->_acl->isAllowed($this, 'w') && !$this->isReference()) {
            throw new ForbiddenException('not allowed to restore node '.$this->name, ForbiddenException::NOT_ALLOWED_TO_UNDELETE);
        }

        $parent = $this->getParent();
        if ($parent->isDeleted()) {
            throw new Exception\Conflict('could not restore node '.$this->name.' into a deleted parent', Exception\Conflict::DELETED_PARENT);
        }

        if ($parent->childExists($this->name)) {
            if (NodeInterface::CONFLICT_MERGE === $conflict) {
                $new = $this->copyTo($parent, $conflict, null, true, NodeInterface::DELETED_INCLUDE);

                if ($new->getId() != $this->getId()) {
                    $this->delete(true);
                }
            } elseif (NodeInterface::CONFLICT_RENAME === $conflict) {
                $this->setName($this->getDuplicateName());
                $this->raw_attributes['name'] = $this->name;
            } else {
                throw new Exception\Conflict('a node called '.$this->name.' does already exists in this collection', Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS);
            }
        }

        if (null === $recursion) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->storage = $this->_parent->getStorage()->undelete($this);
        $this->deleted = false;

        $this->save([
                'storage',
                'name',
                'deleted',
            ], [], $recursion, $recursion_first);

        if ($this instanceof File || $this->isReference() || $this->isMounted() || $this->isFiltered()) {
            return true;
        }

        return $this->doRecursiveAction(function ($node) use ($conflict, $recursion) {
            $node->undelete($conflict, $recursion, false);
        }, NodeInterface::DELETED_ONLY);
    }

    /**
     * Is node deleted?
     */
    public function isDeleted(): bool
    {
        return $this->deleted instanceof UTCDateTime;
    }

    /**
     * Get last modified timestamp.
     */
    public function getLastModified(): int
    {
        if ($this->changed instanceof UTCDateTime) {
            return (int) $this->changed->toDateTime()->format('U');
        }

        return 0;
    }

    /**
     * Get unique id.
     */
    public function getId(): ?ObjectId
    {
        return $this->_id;
    }

    /**
     * Get parent.
     */
    public function getParent(): ?Collection
    {
        return $this->_parent;
    }

    /**
     * Get parents.
     */
    public function getParents(?NodeInterface $node = null, array $parents = []): array
    {
        if (null === $node) {
            $node = $this;
        }

        if ($node->isInRoot()) {
            return $parents;
        }
        $parent = $node->getParent();
        $parents[] = $parent;

        return $node->getParents($parent, $parents);
    }

    /**
     * Get as zip.
     */
    public function getZip(): void
    {
        set_time_limit(0);
        $archive = new ZipStream($this->name.'.zip');
        $this->zip($archive, false);
        $archive->finish();
    }

    /**
     * Create zip.
     */
    public function zip(ZipStream $archive, bool $self = true, ?NodeInterface $parent = null, string $path = '', int $depth = 0): bool
    {
        if (null === $parent) {
            $parent = $this;
        }

        if ($parent instanceof Collection) {
            $children = $parent->getChildren();

            if (true === $self && 0 === $depth) {
                $path = $parent->getName().DIRECTORY_SEPARATOR;
            } elseif (0 === $depth) {
                $path = '';
            } elseif (0 !== $depth) {
                $path .= DIRECTORY_SEPARATOR.$parent->getName().DIRECTORY_SEPARATOR;
            }

            foreach ($children as $child) {
                $name = $path.$child->getName();

                if ($child instanceof Collection) {
                    $this->zip($archive, $self, $child, $name, ++$depth);
                } elseif ($child instanceof File) {
                    try {
                        $resource = $child->get();
                        if ($resource !== null) {
                            $archive->addFileFromStream($name, $resource);
                        }
                    } catch (\Exception $e) {
                        $this->_logger->error('failed add file ['.$child->getId().'] to zip stream', [
                            'category' => get_class($this),
                            'exception' => $e,
                        ]);
                    }
                }
            }
        } elseif ($parent instanceof File) {
            $resource = $parent->get();
            if ($resource !== null) {
                $archive->addFileFromStream($parent->getName(), $resource);
            }
        }

        return true;
    }

    /**
     * Get mime type.
     */
    public function getContentType(): string
    {
        return $this->mime;
    }

    /**
     * Is reference.
     */
    public function isReference(): bool
    {
        return $this->reference instanceof ObjectId;
    }

    /**
     * Set app attributes.
     */
    public function setAppAttributes(string $namespace, array $attributes): NodeInterface
    {
        $this->app[$namespace] = $attributes;
        $this->save('app.'.$namespace);

        return $this;
    }

    /**
     * Set app attribute.
     */
    public function setAppAttribute(string $namespace, string $attribute, $value): NodeInterface
    {
        if (!isset($this->app[$namespace])) {
            $this->app[$namespace] = [];
        }

        $this->app[$namespace][$attribute] = $value;
        $this->save('app.'.$namespace);

        return $this;
    }

    /**
     * Remove app attribute.
     */
    public function unsetAppAttributes(string $namespace): NodeInterface
    {
        if (isset($this->app[$namespace])) {
            unset($this->app[$namespace]);
            $this->save('app.'.$namespace);
        }

        return $this;
    }

    /**
     * Remove app attribute.
     */
    public function unsetAppAttribute(string $namespace, string $attribute): NodeInterface
    {
        if (isset($this->app[$namespace][$attribute])) {
            unset($this->app[$namespace][$attribute]);
            $this->save([], ['app.'.$namespace.'.'.$attribute]);
        }

        return $this;
    }

    /**
     * Get app attribute.
     */
    public function getAppAttribute(string $namespace, string $attribute)
    {
        if (isset($this->app[$namespace][$attribute])) {
            return $this->app[$namespace][$attribute];
        }

        return null;
    }

    /**
     * Get app attributes.
     */
    public function getAppAttributes(string $namespace): array
    {
        if (isset($this->app[$namespace])) {
            return $this->app[$namespace];
        }

        return [];
    }

    /**
     * Set meta attributes.
     */
    public function setMetaAttributes(array $attributes): NodeInterface
    {
        $attributes = $this->validateMetaAttributes($attributes);
        foreach ($attributes as $attribute => $value) {
            if (empty($value) && isset($this->meta[$attribute])) {
                unset($this->meta[$attribute]);
            } elseif (!empty($value)) {
                $this->meta[$attribute] = $value;
            }
        }

        $this->save('meta');

        return $this;
    }

    /**
     * Get meta attributes as array.
     */
    public function getMetaAttributes(array $attributes = []): array
    {
        if (empty($attributes)) {
            return $this->meta;
        }
        if (is_array($attributes)) {
            return array_intersect_key($this->meta, array_flip($attributes));
        }
    }

    /**
     * Mark node as readonly.
     */
    public function setReadonly(bool $readonly = true): bool
    {
        $this->readonly = $readonly;
        $this->storage = $this->_parent->getStorage()->readonly($this, $readonly);

        return $this->save(['readonly', 'storage']);
    }

    /**
     * Mark node as self-destroyable.
     */
    public function setDestroyable(?UTCDateTime $ts): bool
    {
        $this->destroy = $ts;

        if (null === $ts) {
            return $this->save([], 'destroy');
        }

        return $this->save('destroy');
    }

    /**
     * Get original raw attributes before any processing.
     */
    public function getRawAttributes(): array
    {
        return $this->raw_attributes;
    }

    /**
     * Check if node is in root.
     */
    public function isInRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * Check if node is an instance of the actual root collection.
     */
    public function isRoot(): bool
    {
        return null === $this->_id && ($this instanceof Collection);
    }

    /**
     * Resolve node path.
     */
    public function getPath(): string
    {
        $path = '';
        foreach (array_reverse($this->getParents()) as $parent) {
            $path .= DIRECTORY_SEPARATOR.$parent->getName();
        }

        $path .= DIRECTORY_SEPARATOR.$this->getName();

        return $path;
    }

    /**
     * Save node attributes.
     *
     * @param array|string $attributes
     * @param array|string $remove
     * @param string       $recursion
     */
    public function save($attributes = [], $remove = [], ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->_acl->isAllowed($this, 'w') && !$this->isReference()) {
            throw new ForbiddenException('not allowed to modify node '.$this->name, ForbiddenException::NOT_ALLOWED_TO_MODIFY);
        }

        if ($this instanceof Collection && $this->isRoot()) {
            return false;
        }

        $remove = (array) $remove;
        $attributes = (array) $attributes;
        $this->_hook->run(
            'preSaveNodeAttributes',
            [$this, &$attributes, &$remove, &$recursion, &$recursion_first]
        );

        try {
            $set = [];
            $values = $this->getAttributes();
            foreach ($attributes as $attr) {
                $set[$attr] = $this->getArrayValue($values, $attr);
            }

            $update = [];
            if (!empty($set)) {
                $update['$set'] = $set;
            }

            if (!empty($remove)) {
                $remove = array_fill_keys($remove, 1);
                $update['$unset'] = $remove;
            }

            if (empty($update)) {
                return false;
            }
            $result = $this->_db->storage->updateOne([
                    '_id' => $this->_id,
                ], $update);

            $this->_hook->run(
                'postSaveNodeAttributes',
                [$this, $attributes, $remove, $recursion, $recursion_first]
            );

            $this->_logger->info('modified node attributes of ['.$this->_id.']', [
                'category' => get_class($this),
                'params' => $update,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->_logger->error('failed modify node attributes of ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Duplicate name with a uniqid within name.
     */
    public function getDuplicateName(?string $name = null, ?string $class = null): string
    {
        if (null === $name) {
            $name = $this->name;
        }

        if (null === $class) {
            $class = get_class($this);
        }

        if ($class === Collection::class) {
            return $name.' ('.substr(uniqid('', true), -4).')';
        }

        $ext = substr(strrchr($name, '.'), 1);
        if (false === $ext) {
            return $name.' ('.substr(uniqid('', true), -4).')';
        }

        $name = substr($name, 0, -(strlen($ext) + 1));

        return $name.' ('.substr(uniqid('', true), -4).')'.'.'.$ext;
    }

    /**
     * Prepare lock.
     */
    protected function prepareLock(string $identifier, int $ttl = 1800): array
    {
        return [
             'owner' => $this->_user->getId(),
            'created' => new UTCDateTime(),
            'id' => $identifier,
            'expire' => new UTCDateTime((time() + $ttl) * 1000),
        ];
    }

    /**
     * Get array value via string path.
     */
    protected function getArrayValue(iterable $array, string $path, string $separator = '.')
    {
        if (isset($array[$path])) {
            return $array[$path];
        }
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return;
            }

            $array = $array[$key];
        }

        return $array;
    }

    /**
     * Validate meta attributes.
     */
    protected function validateMetaAttributes(array $attributes): array
    {
        foreach ($attributes as $attribute => $value) {
            $const = __CLASS__.'::META_'.strtoupper($attribute);
            if (!defined($const)) {
                throw new Exception('meta attribute '.$attribute.' is not valid');
            }

            if ($attribute === NodeInterface::META_TAGS && !empty($value) && (!is_array($value) || array_filter($value, 'is_string') != $value)) {
                throw new Exception('tags meta attribute must be an array of strings');
            }

            if ($attribute !== NodeInterface::META_TAGS && !is_string($value)) {
                throw new Exception($attribute.' meta attribute must be a string');
            }
        }

        return $attributes;
    }

    /**
     * Completly remove node.
     */
    abstract protected function _forceDelete(): bool;
}
