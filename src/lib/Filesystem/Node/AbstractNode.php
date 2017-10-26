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

use Balloon\App\AppInterface;
use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Helper;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Normalizer;
use PHPZip\Zip\Stream\ZipStream;
use Sabre\DAV;

abstract class AbstractNode implements NodeInterface, DAV\INode
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
     * Is collection.
     *
     * @var bool
     */
    protected $directory = false;

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
    protected $app_attributes = [];

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
     * @var Logger
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
     * Storage.
     *
     * @var Storage
     */
    protected $storage_handler;

    /**
     * Storage.
     *
     * @var array
     */
    protected $storage = [
        'adapter' => 'gridfs',
    ];

    /**
     * Initialize.
     *
     * @param array      $attributes
     * @param Filesystem $fs
     */
    public function __construct(?array $attributes, Filesystem $fs)
    {
        $this->_fs = $fs;
        $this->_db = $fs->getDatabase();
        $this->_user = $fs->getUser();
        $this->_server = $fs->getServer();
        $this->_logger = $this->_server->getLogger();
        $this->_hook = $this->_server->getHook();

        $this->storage_handler = $fs->getServer()->getStorage();

        if (null !== $attributes) {
            foreach ($attributes as $attr => $value) {
                $this->{$attr} = $value;
            }

            $this->raw_attributes = $attributes;
        }
    }

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
     * Get property.
     *
     * @return mixed
     */
    public function __call(string $attribute, array $params = [])
    {
        $prefix = 'get';
        $attr = strtolower(substr($attribute, 3));
        if (property_exists($this, $attr)) {
            return $this->{$attr};
        }

        throw new Exception('method '.$attribute.' does not exists');
    }

    /**
     * Set filesystem.
     *
     * @return NodeInterface
     */
    public function setFilesystem(Filesystem $fs)
    {
        $this->_fs = $fs;

        return $this;
    }

    /**
     * Get filesystem.
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->_fs;
    }

    /**
     * Check if $node is a sub node of any parent nodes of this node.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    public function isSubNode(NodeInterface $node): bool
    {
        if ($node->getId() === $this->_id) {
            return true;
        }

        foreach ($node->getParents() as $node) {
            if ($node->getId() === $this->_id) {
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
     *
     * @param Collection $parent
     * @param int        $conflict
     *
     * @return NodeInterface
     */
    public function setParent(Collection $parent, int $conflict = NodeInterface::CONFLICT_NOACTION): NodeInterface
    {
        if ($this->parent === $parent->getId()) {
            throw new Exception\Conflict(
                'source node '.$this->name.' is already in the requested parent folder',
                Exception\Conflict::ALREADY_THERE
            );
        }
        if ($this->isSubNode($parent)) {
            throw new Exception\Conflict(
                'node called '.$this->name.' can not be moved into itself',
                Exception\Conflict::CANT_BE_CHILD_OF_ITSELF
            );
        }
        if (!$this->isAllowed('w') && !$this->isReference()) {
            throw new Exception\Forbidden(
                'not allowed to move node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_MOVE
            );
        }

        $exists = $parent->childExists($this->name);
        if (true === $exists && NodeInterface::CONFLICT_NOACTION === $conflict) {
            throw new Exception\Conflict(
                'a node called '.$this->name.' does already exists in this collection',
                Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
            );
        }
        if ($this->isShared() && $this instanceof Collection && $parent->isShared()) {
            throw new Exception\Conflict(
                'a shared folder can not be a child of a shared folder too',
                Exception\Conflict::SHARED_NODE_CANT_BE_CHILD_OF_SHARE
            );
        }
        if ($parent->isDeleted()) {
            throw new Exception\Conflict(
                'cannot move node into a deleted collction',
                Exception\Conflict::DELETED_PARENT
            );
        }

        if (true === $exists && NodeInterface::CONFLICT_RENAME === $conflict) {
            $this->setName($this->getDuplicateName());
            $this->raw_attributes['name'] = $this->name;
        }

        if ($this instanceof Collection) {
            $this->getChildrenRecursive($this->getRealId(), $shares);

            if (!empty($shares) && $parent->isShared()) {
                throw new Exception\Conflict(
                    'folder contains a shared folder',
                    Exception\Conflict::NODE_CONTAINS_SHARED_NODE
                );
            }
        }

        if ($parent->isSpecial() && $this->shared !== $parent->getShareId() || !$parent->isSpecial() && $this->isShareMember()) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete();

            return $new;
        }

        if (true === $exists && NodeInterface::CONFLICT_MERGE === $conflict) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete(true/*, false, false*/);

            return $new;
        }

        $this->parent = $parent->getRealId();
        $this->owner = $this->_user->getId();

        $this->save(['parent', 'shared', 'owner']);

        return $this;
    }

    /**
     * Copy node.
     *
     * @param Collection $parent
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
     *
     * @return NodeInterface
     */
    abstract public function copyTo(Collection $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): NodeInterface;

    /**
     * Get share id.
     *
     * @param bool $reference
     *
     * @return ObjectId
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
     * Get share node.
     *
     * @param bool $reference
     *
     * @return Collection
     */
    public function getShareNode(): ?Collection
    {
        if ($this->isSpecial()) {
            return $this->_fs->findNodeWithId($this->getShareId(true));
        }

        return null;
    }

    /**
     * Is node marked as readonly?
     *
     * @return bool
     */
    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * Request is from node owner?
     *
     * @return bool
     */
    public function isOwnerRequest(): bool
    {
        return null !== $this->_user && $this->owner === $this->_user->getId();
    }

    /**
     * Check if node is kind of special.
     *
     * @return bool
     */
    public function isSpecial(): bool
    {
        if ($this->isShared()) {
            return true;
        }
        if ($this->isReference()) {
            return true;
        }
        if ($this->isShareMember() /*&& $this instanceof Collection*/) {
            return true;
        }

        return false;
    }

    /**
     * Check if node is a sub node of a share.
     *
     * @return bool
     */
    public function isShareMember(): bool
    {
        return $this->shared instanceof ObjectId && !$this->isReference();
    }

    /**
     * Is share.
     *
     * @return bool
     */
    public function isShare(): bool
    {
        return true === $this->shared && !$this->isReference();
    }

    /**
     * Is share (Reference or master share).
     *
     * @return bool
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
     *
     * @param string $name
     *
     * @return bool
     */
    public function setName($name): bool
    {
        $name = $this->checkName($name);

        if ($this->getParent()->childExists($name)) {
            throw new Exception\Conflict(
                'a node called '.$name.' does already exists in this collection',
                Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
            );
        }

        $this->name = $name;

        return $this->save('name');
    }

    /**
     * Check name.
     *
     * @param string $name
     *
     * @return string
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
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check acl.
     *
     * @param string $privilege
     *
     * @return bool
     */
    public function isAllowed(string $privilege = 'r'): bool
    {
        $acl = null;
        $share = null;

        $this->_logger->debug('check acl for ['.$this->_id.'] with privilege ['.$privilege.']', [
            'category' => get_class($this),
        ]);

        if (null === $this->_user) {
            $this->_logger->debug('system acl call, grant full access', [
                'category' => get_class($this),
            ]);

            return true;
        }

        /* TODO: writeonly does not work with this part:
        if($this->_user->getId() == $this->owner) {
            $this->_logger->debug('owner request detected for ['.$this->_id.'], grant full access', [
                'category' => get_class($this),
            ]);

            return true;
        }
        */

        $priv = $this->getAclPrivilege();

        $result = false;

        if ('w+' === $priv && $this->isOwnerRequest()) {
            $result = true;
        } elseif ($this->isShared() || $this->isReference()) {
            if ('r' === $privilege && ('r' === $priv || 'w' === $priv || 'rw' === $priv)) {
                $result = true;
            } elseif ('w' === $privilege && ('w' === $priv || 'rw' === $priv)) {
                $result = true;
            }
        } else {
            if ('r' === $privilege && ('r' === $priv || 'rw' === $priv)) {
                $result = true;
            } elseif ('w' === $privilege && ('w' === $priv || 'rw' === $priv)) {
                $result = true;
            }
        }

        $this->_logger->debug('check acl for node ['.$this->_id.'], requested privilege ['.$privilege.']', [
            'category' => get_class($this),
            'params' => ['privileges' => $priv],
        ]);

        return $result;
    }

    /**
     * Get privilege.
     *
     * rw - READ/WRITE
     * r  - READ(only)
     * w  - WRITE(only)
     * d  - DENY
     *
     * @return string
     */
    public function getAclPrivilege()
    {
        $result = false;
        $acl = [];
        $user = $this->_user;

        if ($this->isShareMember()) {
            try {
                $share = $this->_fs->findRawNode($this->shared);
            } catch (\Exception $e) {
                $this->_logger->error('could not found share node ['.$this->shared.'] for share child node ['.$this->_id.'], dead reference?', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                return 'd';
            }

            if ((string) $share['owner'] === (string) $user->getId()) {
                return 'rw';
            }

            $acl = $share['acl'];
        } elseif ($this->isReference() && $this->isOwnerRequest()) {
            try {
                $share = $this->_fs->findRawNode($this->reference);
            } catch (\Exception $e) {
                $this->_logger->error('could not found share node ['.$this->shared.'] for reference ['.$this->_id.'], dead reference?', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                $this->_forceDelete();

                return 'd';
            }

            if ($share['deleted'] instanceof UTCDateTime || true !== $share['shared']) {
                $this->_logger->error('share node ['.$share['_id'].'] has been deleted, dead reference?', [
                    'category' => get_class($this),
                ]);

                $this->_forceDelete();

                return 'd';
            }

            $acl = $share['acl'];
        } elseif (!$this->isOwnerRequest()) {
            $this->_logger->warning('user ['.$this->_user->getUsername().'] not allowed to access non owned node ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            return 'd';
        } elseif ($this->isOwnerRequest()) {
            return 'rw';
        }

        if (!is_array($acl)) {
            return 'd';
        }

        if (array_key_exists('user', $acl)) {
            foreach ($acl['user'] as $rule) {
                if (array_key_exists('user', $rule) && $rule['user'] === $user->getUsername() && array_key_exists('priv', $rule)) {
                    $result = $rule['priv'];
                } elseif (array_key_exists('ldapdn', $rule) && $rule['ldapdn'] === $user->getLdapDn() && array_key_exists('priv', $rule)) {
                    $result = $rule['priv'];
                }

                if ('d' === $result) {
                    return $result;
                }
            }
        }

        if (array_key_exists('group', $acl)) {
            $groups = $user->getGroups();

            foreach ($acl['group'] as $rule) {
                if (array_key_exists('group', $rule) && in_array($rule['group'], $groups, true) && array_key_exists('priv', $rule)) {
                    $group_result = $rule['priv'];

                    if ('d' === $group_result) {
                        return $group_result;
                    }
                    if (false === $result) {
                        $result = $group_result;
                    } elseif ('r' === $result && ('w' === $group_result || 'rw' === $group_result)) {
                        $result = $group_result;
                    } elseif ('rw' === $group_result) {
                        $result = $group_result;
                    }
                }
            }
        }

        if (false === $result) {
            return 'd';
        }

        return $result;
    }

    /**
     * Get attribute.
     *
     * @param string $attribute
     *
     * @return mixed
     */
    public function getAttribute(string $attribute)
    {
        $attributes = $this->getAttributes([$attribute]);

        if (!isset($attributes[$attribute])) {
            throw new Exception('attribute was not found');
        }

        return $attributes[$attribute];
    }

    /**
     * Undelete.
     *
     * @param int    $conflict
     * @param string $recursion
     * @param bool   $recursion_first
     *
     * @return bool
     */
    public function undelete(int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->isAllowed('w')) {
            throw new Exception\Forbidden(
                'not allowed to restore node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_UNDELETE
            );
        }
        if (!$this->isDeleted()) {
            throw new Exception\Conflict(
                'node is not deleted, skip restore',
                Exception\Conflict::NOT_DELETED
            );
        }

        $parent = $this->getParent();
        if ($parent->isDeleted()) {
            throw new Exception\Conflict(
                'could not restore node '.$this->name.' into a deleted parent',
                Exception\Conflict::DELETED_PARENT
            );
        }

        if ($parent->childExists($this->name)) {
            if (NodeInterface::CONFLICT_MERGE === $conflict) {
                $this->copyTo($parent, $conflict);
                $this->delete(true);
            } elseif (NodeInterface::CONFLICT_RENAME === $conflict) {
                $this->setName($this->getDuplicateName());
                $this->raw_attributes['name'] = $this->name;
            } else {
                throw new Exception\Conflict(
                    'a node called '.$this->name.' does already exists in this collection',
                    Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
                );
            }
        }

        if (null === $recursion) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->deleted = false;

        if ($this instanceof File) {
            $current = $this->version;
            $new = $this->increaseVersion();

            $this->history[] = [
                'version' => $new,
                'changed' => $this->changed,
                'user' => $this->owner,
                'type' => File::HISTORY_UNDELETE,
                'storage' => $this->storage,
                'size' => $this->size,
                'mime' => $this->mime,
            ];

            return $this->save([
                'name',
                'deleted',
                'history',
                'version',
            ], [], $recursion, $recursion_first);
        }
        $this->save([
                'name',
                'deleted',
            ], [], $recursion, $recursion_first);

        return $this->doRecursiveAction(
                'undelete',
                [
                    'conflict' => $conflict,
                    'recursion' => $recursion,
                    'recursion_first' => false,
                ],
                NodeInterface::DELETED_ONLY
            );

        return true;
    }

    /**
     * Is node deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted instanceof UTCDateTime;
    }

    /**
     * Get last modified timestamp.
     *
     * @return int
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
     *
     * @return ObjectId|string
     */
    public function getId(bool $string = false)
    {
        if (true === $string) {
            return (string) $this->_id;
        }

        return $this->_id;
    }

    /**
     * Get parent.
     *
     * @return Collection
     */
    public function getParent(): ?Collection
    {
        try {
            if ($this->isRoot()) {
                return null;
            }
            if ($this->isInRoot()) {
                return $this->_fs->getRoot();
            }
            $parent = $this->_fs->findNodeWithId($this->parent);
            if ($parent->isShare() && !$parent->isOwnerRequest() && null !== $this->_user) {
                $node = $this->_db->storage->findOne([
                        'owner' => $this->_user->getId(),
                        'shared' => true,
                        'reference' => $this->parent,
                    ]);

                return new Collection($node, $this->_fs);
            }

            return $parent;
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound(
                'parent node '.$this->parent.' could not be found',
                Exception\NotFound::PARENT_NOT_FOUND
            );
        }
    }

    /**
     * Get parents.
     *
     * @param array $parents
     *
     * @return array
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
        return $parents;
    }

    /**
     * Download.
     */
    abstract public function get();

    /**
     * Get as zip.
     */
    public function getZip(): void
    {
        $temp = $this->_server->getTempDir().DIRECTORY_SEPARATOR.'zip';
        if (!file_exists($temp)) {
            mkdir($temp, 0700, true);
        }

        ZipStream::$temp = $temp;
        $archive = new ZipStream($this->name.'.zip', 'application/zip', $this->name.'.zip');
        $this->zip($archive, false);
        $archive->finalize();
    }

    /**
     * Create zip.
     *
     * @param ZipStream     $archive
     * @param bool          $self    true means that the zip represents the collection itself instead a child of the zip
     * @param NodeInterface $parent
     * @param string        $path
     * @param int           $depth
     *
     * @return bool
     */
    public function zip(ZipStream $archive, bool $self = true, ?NodeInterface $parent = null, string $path = '', int $depth = 0): bool
    {
        if (null === $parent) {
            $parent = $this;
        }

        if ($parent instanceof Collection) {
            $children = $parent->getChildNodes();

            if (true === $self && 0 === $depth) {
                $path = $parent->getName();
                $archive->addDirectory($path);
                $path .= DIRECTORY_SEPARATOR;
            } elseif (0 === $depth) {
                $path = '';
            } elseif (0 !== $depth) {
                $path .= DIRECTORY_SEPARATOR.$parent->getName().DIRECTORY_SEPARATOR;
            }

            foreach ($children as $child) {
                $name = $path.$child->getName();

                if ($child instanceof Collection) {
                    $archive->addDirectory($name);
                    $this->zip($archive, $self, $child, $name, ++$depth);
                } elseif ($child instanceof File) {
                    try {
                        $archive->addFile($child->get(), $name);
                    } catch (\Exception $e) {
                        $this->_logger->error('failed add file ['.$child->getId().'] to zip stream', [
                            'category' => get_class($this),
                            'exception' => $e,
                        ]);
                    }
                }
            }
        } elseif ($parent instanceof File) {
            $archive->addFile($parent->get(), $parent->getName());
        }

        return true;
    }

    /**
     * Is reference.
     *
     *  @return bool
     */
    public function isReference(): bool
    {
        return $this->reference instanceof ObjectId;
    }

    /**
     * Set app attributes.
     *
     * @param AppInterface $app
     * @param array        $attributes
     *
     * @return NodeInterface
     */
    public function setAppAttributes(AppInterface $app, array $attributes): NodeInterface
    {
        $this->app_attributes[$app->getName()] = $attributes;
        $this->save('app_attributes');

        return $this;
    }

    /**
     * Set app attribute.
     *
     * @param AppInterface $app
     * @param string       $attribute
     * @param mixed        $value
     *
     * @return NodeInterface
     */
    public function setAppAttribute(AppInterface $app, string $attribute, $value): NodeInterface
    {
        if (!isset($this->app_attributes[$app->getName()])) {
            $this->app_attributes[$app->getName()] = [];
        }

        $this->app_attributes[$app->getName()][$attribute] = $value;
        $this->save('app_attributes');

        return $this;
    }

    /**
     * Remove app attribute.
     *
     * @param AppInterface $app
     *
     * @return NodeInterface
     */
    public function unsetAppAttributes(AppInterface $app): NodeInterface
    {
        if (isset($this->app_attributes[$app->getName()])) {
            unset($this->app_attributes[$app->getName()]);
            $this->save('app_attributes');
        }

        return $this;
    }

    /**
     * Remove app attribute.
     *
     * @param AppInterface $app
     * @param string       $attribute
     *
     * @return NodeInterface
     */
    public function unsetAppAttribute(AppInterface $app, string $attribute): NodeInterface
    {
        if (isset($this->app_attributes[$app->getName()], $this->app_attributes[$app->getName()][$attribute])) {
            unset($this->app_attributes[$app->getName()][$attribute]);
            $this->save('app_attributes');
        }

        return $this;
    }

    /**
     * Get app attribute.
     *
     * @param AppInterface $app
     * @param string       $attribute
     *
     * @return mixed
     */
    public function getAppAttribute(AppInterface $app, string $attribute)
    {
        if (isset($this->app_attributes[$app->getName()], $this->app_attributes[$app->getName()][$attribute])) {
            return $this->app_attributes[$app->getName()][$attribute];
        }

        return null;
    }

    /**
     * Get app attributes.
     *
     * @param AppInterface $app
     *
     * @return array
     */
    public function getAppAttributes(AppInterface $app): array
    {
        if (isset($this->app_attributes[$app->getName()])) {
            return $this->app_attributes[$app->getName()];
        }

        return [];
    }

    /**
     * Set meta attribute.
     *
     * @param   array|string
     * @param mixed $value
     * @param mixed $attributes
     *
     * @return NodeInterface
     */
    public function setMetaAttribute($attributes, $value = null): NodeInterface
    {
        $this->meta = self::validateMetaAttribute($attributes, $value, $this->meta);
        $this->save('meta');

        return $this;
    }

    /**
     * validate meta attribut.
     *
     * @param array|string $attributes
     * @param mixed        $value
     * @param array        $set
     *
     * @return array
     */
    public static function validateMetaAttribute($attributes, $value = null, array $set = []): array
    {
        if (is_string($attributes)) {
            $attributes = [
                $attributes => $value,
            ];
        }

        foreach ($attributes as $attribute => $value) {
            $const = __CLASS__.'::META_'.strtoupper($attribute);
            if (!defined($const)) {
                throw new Exception('meta attribute '.$attribute.' is not valid');
            }

            if (empty($value) && array_key_exists($attribute, $set)) {
                unset($set[$attribute]);
            } else {
                $set[$attribute] = $value;
            }
        }

        return $set;
    }

    /**
     * Get meta attributes as array.
     *
     * @param array|string $attribute Specify attributes to return
     *
     * @return array|string
     */
    public function getMetaAttribute($attribute = [])
    {
        if (is_string($attribute)) {
            if (isset($this->meta[$attribute])) {
                return $this->meta[$attribute];
            }
        } elseif (empty($attribute)) {
            return $this->meta;
        } elseif (is_array($attribute)) {
            return array_intersect_key($this->meta, array_flip($attribute));
        }
    }

    /**
     * Mark node as readonly.
     *
     * @param bool $readonly
     *
     * @return bool
     */
    public function setReadonly(bool $readonly = true): bool
    {
        $this->readonly = $readonly;

        return $this->save('readonly');
    }

    /**
     * Mark node as self-destroyable.
     *
     * @param UTCDateTime $ts
     *
     * @return bool
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
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     *
     * @param bool   $force
     * @param bool   $recursion_first
     * @param string $recursion
     *
     * @return bool
     */
    abstract public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool;

    /**
     * Get original raw attributes before any processing.
     *
     * @return array|\MongoDB\BSON\Document
     */
    public function getRawAttributes()
    {
        return $this->raw_attributes;
    }

    /**
     * Check if node is in root.
     *
     * @return bool
     */
    public function isInRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * Check if node is an instance of the actual root collection.
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return null === $this->_id && ($this instanceof Collection);
    }

    /**
     * Resolve node path.
     *
     * @return string
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
     * @param bool         $recursion_first
     *
     * @return bool
     */
    public function save($attributes = [], $remove = [], ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->isAllowed('w') && !$this->isReference()) {
            throw new Exception\Forbidden(
                'not allowed to modify node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_MODIFY
            );
        }

        $remove = (array) $remove;
        $attributes = (array) $attributes;
        $this->_hook->run(
            'preSaveNodeAttributes',
            [$this, &$attributes, &$remove, &$recursion, &$recursion_first]
        );

        try {
            $set = [];

            foreach ($attributes as $attr) {
                $set[$attr] = $this->{$attr};
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
     * Check node.
     */
    protected function _verifyAccess()
    {
        if (!$this->isAllowed('r')) {
            throw new Exception\Forbidden(
                'not allowed to access node',
                Exception\Forbidden::NOT_ALLOWED_TO_ACCESS
            );
        }

        if ($this->destroy instanceof UTCDateTime && $this->destroy->toDateTime()->format('U') <= time()) {
            $this->_logger->info('node ['.$this->_id.'] is not accessible anmyore, destroy node cause of expired destroy flag', [
                'category' => get_class($this),
            ]);

            $this->delete(true);

            throw new Exception\Conflict('node is not available anymore');
        }
    }

    /**
     * Get Attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function getAttributes(array $attributes = []): array
    {
        $meta = [];
        $clean = [];
        $apps = [];

        foreach ($attributes as $key => $attr) {
            $keys = explode('.', $attr);
            $prefix = array_shift($keys);

            if ('file' === $prefix && ($this instanceof Collection)) {
                continue;
            }
            if ('collection' === $prefix && ($this instanceof File)) {
                continue;
            }
            if (('file' === $prefix || 'collection' === $prefix) && count($keys) > 1) {
                $prefix = array_shift($keys);
            }

            if ('apps' === $prefix && count($keys) > 1) {
                $apps[] = implode('.', $keys);
            } elseif ('meta' === $prefix && 1 === count($keys)) {
                $meta[] = $keys[0];
            } elseif (1 === count($keys)) {
                $clean[] = $keys[0];
            } else {
                $clean[] = $attr;
            }
        }

        if (count($meta) > 0) {
            $clean[] = 'meta';
        }
        if (count($apps) > 0) {
            $clean[] = 'apps';
        }

        $attributes = $clean;

        try {
            $sharenode = $this->getShareNode();
        } catch (\Exception $e) {
            $sharenode = null;
        }

        $build = [];

        foreach ($attributes as $key => $attr) {
            switch ($attr) {
                case 'id':
                    $build['id'] = (string) $this->_id;

                break;
                case 'name':
                case 'mime':
                case 'readonly':
                case 'directory':
                    $build[$attr] = $this->{$attr};

                break;
                case 'parent':
                    try {
                        $parent = $this->getParent();
                        if (null === $parent || null === $parent->getId()) {
                            $build[$attr] = null;
                        } else {
                            $build[$attr] = (string) $parent->getId();
                        }
                    } catch (\Exception $e) {
                        $build[$attr] = null;
                    }

                break;
                case 'meta':
                    $build['meta'] = (object) $this->getMetaAttribute($meta);

                break;
                case 'size':
                    $build['size'] = $this->getSize();

                break;
                case 'deleted':
                case 'changed':
                case 'created':
                case 'destroy':
                    if ($this->{$attr} instanceof UTCDateTime) {
                        $build[$attr] = Helper::DateTimeToUnix($this->{$attr});
                    } else {
                        $build[$attr] = $this->{$attr};
                    }

                break;
                case 'path':
                    try {
                        $build['path'] = $this->getPath();
                    } catch (\Balloon\Exception\NotFound $e) {
                        $build['path'] = null;
                    }

                break;
                case 'shared':
                    if (true === $this->directory) {
                        $build['shared'] = $this->isShared();
                    }

                break;
                case 'filtered':
                    if (true === $this->directory) {
                        $build['filtered'] = $this->isCustomFilter();
                    }

                break;
                case 'reference':
                    if (true === $this->directory) {
                        $build['reference'] = $this->isReference();
                    }

                break;
                case 'share':
                    if ($this->isSpecial() && null !== $sharenode) {
                        $build['share'] = $sharenode->getName();
                    } else {
                        $build['share'] = false;
                    }

                break;
                case 'access':
                    if ($this->isSpecial() && null !== $sharenode) {
                        $build['access'] = $sharenode->getAclPrivilege();
                    }

                break;
                case 'shareowner':
                    if ($this->isSpecial() && null !== $sharenode) {
                        $build['shareowner'] = $this->_server->getUserById($this->_fs->findRawNode($this->getShareId())['owner'])
                          ->getUsername();
                    }

                break;
                case 'apps':
                    $this->build['apps'] = [];
                    foreach ($this->server->getApps()->getApps($apps) as $app) {
                        $this->build['apps'][$app->getName()] = $app->getAttributes($this);
                    }

                break;
            }
        }

        return $build;
    }

    /**
     * Duplicate name with a uniqid within name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getDuplicateName(?string $name = null): string
    {
        if (null === $name) {
            $name = $this->name;
        }

        if ($this instanceof Collection) {
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
     * Completly remove node.
     *
     * @return bool
     */
    abstract protected function _forceDelete(): bool;
}
