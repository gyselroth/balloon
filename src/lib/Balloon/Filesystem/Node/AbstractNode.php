<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use \Sabre\DAV;
use \Balloon\Exception;
use \Balloon\Helper;
use \Balloon\User;
use \Balloon\Filesystem;
use \PHPZip\Zip\Stream\ZipStream;
use \MongoDB\BSON\ObjectId;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\Model\BSONDocument;
use \Normalizer;

abstract class AbstractNode implements NodeInterface, DAV\INode
{
    /**
     * name max lenght
     */
    const MAX_NAME_LENGTH = 255;


    /**
     * Unique id
     *
     * @var ObjectId
     */
    protected $_id;
    

    /**
     * Node name
     *
     * @var string
     */
    protected $name = '';
    
     
    /**
     * Owner
     *
     * @var ObjectId
     */
    protected $owner;


    /**
     * Meta attributes
     *
     * @var array
     */
    protected $meta = [];
    

    /**
     * Parent collection
     *
     * @var ObjectId
     */
    protected $parent;

    
    /**
     * Is file deleted
     *
     * @var bool|UTCDateTime
     */
    protected $deleted = false;

    
    /**
     * Is collection
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
     * Destory at a certain time
     *
     * @var UTCDateTime
     */
    protected $destroy;


    /**
     * Changed timestamp
     *
     * @var UTCDateTime
     */
    protected $changed;

    
    /**
     * Created timestamp
     *
     * @var UTCDateTime
     */
    protected $created;


    /**
     * Point to antother node (Means this node is reference to $reference)
     *
     * @var ObjectId
     */
    protected $reference;


    /**
     * Share link options
     *
     * @var bool|array
     */
    protected $sharelink = false;


    /**
     * Raw attributes before any processing or modifications
     *
     * @var array
     */
    protected $raw_attributes;

    
    /**
     * Readonly flag
     *
     * @var bool
     */
    protected $readonly = false;

    
    /**
     * Filesystem
     *
     * @var Filesystem
     */
    protected $_fs;


    /**
     * Database
     *
     * @var \MongoDB\Database
     */
    protected $_db;


    /**
     * User
     *
     * @var User
     */
    protected $_user;

    
    /**
     * Logger
     *
     * @var Logger
     */
    protected $_logger;


    /**
     * Plugin
     *
     * @var Plugin
     */
    protected $_hook;
    

    /**
     * Initialize
     *
     * @param  BSONDocument $node
     * @param  Filesystem $fs
     * @return void
     */
    public function __construct(?BSONDocument $node, Filesystem $fs)
    {
        $this->_fs     = $fs;
        $this->_db     = $fs->getDatabase();
        $this->_user   = $fs->getUser();
        $this->_logger = $fs->getLogger();
        $this->_hook   = $fs->getHook();

        if ($node !== null) {
            $node = Helper::convertBSONDocToPhp($node);
            foreach ($node as $attr => $value) {
                $this->{$attr} = $value;
            }

            $this->raw_attributes = $node;
        }
    }


    /**
     * Convert to filename
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }


    /**
     * Get filesystem
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->_fs;
    }


    /**
     * Get property
     *
     * @return mixed
     */
    public function __call(string $attribute, array $params=[])
    {
        $prefix = 'get';
        $attr = strtolower(substr($attribute, 3));
        if (property_exists($this, $attr)) {
            return $this->{$attr};
        } else {
            throw new Exception('method '.$attribute.' does not exists');
        }
    }


    /**
     * Check if $node is a sub node of any parent nodes of this node
     *
     * @param   NodeInterface $node
     * @return  bool
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
     * Move node
     *
     * @param  Collection $parent
     * @param  int $conflict
     * @return NodeInterface
     */
    public function setParent(Collection $parent, int $conflict=NodeInterface::CONFLICT_NOACTION): NodeInterface
    {
        if ($this->parent === $parent->getId()) {
            throw new Exception\Conflict('source node '.$this->name.' is already in the requested parent folder',
                Exception\Conflict::ALREADY_THERE
            );
        } elseif ($this->isSubNode($parent)) {
            throw new Exception\Conflict('node called '.$this->name.' can not be moved into itself',
                Exception\Conflict::CANT_BE_CHILD_OF_ITSELF
            );
        } elseif (!$this->isAllowed('w') && !$this->isReference()) {
            throw new Exception\Forbidden('not allowed to move node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_MOVE
            );
        }
        
        $exists = $parent->childExists($this->name);
        if ($exists === true && $conflict === NodeInterface::CONFLICT_NOACTION) {
            throw new Exception\Conflict('a node called '.$this->name.' does already exists in this collection',
                Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
            );
        } elseif ($this->isShared() && $this instanceof Collection && $parent->isShared()) {
            throw new Exception\Conflict('a shared folder can not be a child of a shared folder too',
                Exception\Conflict::SHARED_NODE_CANT_BE_CHILD_OF_SHARE
            );
        } elseif ($parent->isDeleted()) {
            throw new Exception\Conflict('cannot move node into a deleted collction',
                Exception\Conflict::DELETED_PARENT
            );
        }

        if ($exists === true && $conflict == NodeInterface::CONFLICT_RENAME) {
            $this->setName($this->_getDuplicateName());
            $this->raw_attributes['name'] = $this->name;
        }
        
        if ($this instanceof Collection) {
            $this->getChildrenRecursive($this->getRealId(), $shares);

            if (!empty($shares) && $parent->isShared()) {
                throw new Exception\Conflict('folder contains a shared folder',
                    Exception\Conflict::NODE_CONTAINS_SHARED_NODE
                );
            }
        }

        if ($parent->isSpecial() && $this->shared != $parent->getShareId() || !$parent->isSpecial() && $this->isShareMember()) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete();
            return $new;
        }

        if ($exists === true && $conflict == NodeInterface::CONFLICT_MERGE) {
            $new = $this->copyTo($parent, $conflict);
            $this->delete(true/*, false, false*/);
            return $new;
        }

        $this->parent = $parent->getRealId();
        $this->owner  = $this->_user->getId();

        $this->save(['parent', 'shared', 'owner']);
        return $this;
    }


    /**
     * Copy node
     *
     * @param   Collection $parent
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  NodeInterface
     */
    abstract public function copyTo(Collection $parent, int $conflict=NodeInterface::CONFLICT_NOACTION, ?string $recursion=null, bool $recursion_first=true): NodeInterface;


    /**
     * Get share id
     *
     * @param   bool $reference
     * @return  ObjectId
     */
    public function getShareId(bool $reference=false): ?ObjectId
    {
        if ($this->isReference() && $reference === true) {
            return $this->_id;
        } elseif ($this->isShareMember() && $reference === true) {
            return $this->shared;
        } elseif ($this->isShared() && $this->isReference()) {
            return $this->reference;
        } elseif ($this->isShared()) {
            return $this->_id;
        } elseif ($this->isShareMember()) {
            return $this->shared;
        } else {
            return null;
        }
    }


    /**
     * Check node
     *
     * @return void
     */
    protected function _verifyAccess()
    {
        if (!$this->isAllowed('r')) {
            throw new Exception\Forbidden('not allowed to access node',
                Exception\Forbidden::NOT_ALLOWED_TO_ACCESS
            );
        }

        if ($this->destroy instanceof UTCDateTime && $this->destroy->toDateTime()->format('U') <= time()) {
            $this->_logger->info('node ['.$this->_id.'] is not accessible anmyore, destroy node cause of expired destroy flag', [
                'category' => get_class($this)
            ]);
            
            $this->delete(true);
            throw new Exception\Conflict('node is not available anymore');
        }
    }


    /**
     * Get share node
     *
     * @param   bool $reference
     * @return  Collection
     */
    public function getShareNode(): ?Collection
    {
        if ($this->isSpecial()) {
            return $this->_fs->findNodeWithId($this->getShareId(true));
        } else {
            return null;
        }
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
        return ($this->_user !== null && $this->owner == $this->_user->getId());
    }


    /**
     * Check if node is kind of special
     *
     * @return bool
     */
    public function isSpecial(): bool
    {
        if ($this->isShared()) {
            return true;
        } elseif ($this->isReference()) {
            return true;
        } elseif ($this->isShareMember() /*&& $this instanceof Collection*/) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check if node is a sub node of a share
     *
     * @return bool
     */
    public function isShareMember(): bool
    {
        return ($this->shared instanceof ObjectId && !$this->isReference());
    }


    /**
     * Is share
     *
     * @return bool
     */
    public function isShare(): bool
    {
        return ($this->shared === true && !$this->isReference());
    }


    /**
     * Is share (Reference or master share)
     *
     * @return bool
     */
    public function isShared(): bool
    {
        if ($this->shared === true) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set the name
     *
     * @param  string $name
     * @return bool
     */
    public function setName($name): bool
    {
        $name = $this->checkName($name);

        if ($this->getParent()->childExists($name)) {
            throw new Exception\Conflict('a node called '.$name.' does already exists in this collection',
                Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
            );
        }
        
        $this->name = $name;
        return $this->save('name');
    }
    

    /**
     * Check name
     *
     * @param   string $name
     * @return  string
     */
    public function checkName(string $name): string
    {
        if (preg_match('/([\\\<\>\:\"\/\|\*\?])|(^$)|(^\.$)|(^\..$)/', $name)) {
            throw new Exception\InvalidArgument('name contains invalid characters');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            throw new Exception\InvalidArgument('name is longer than '.self::MAX_NAME_LENGTH.' characters');
        }

        if (!Normalizer::isNormalized($name)) {
            $name = Normalizer::normalize($name);
        }
 
        return $name;
    }


    /**
     * Get the name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * Check acl
     *
     * @param   string $privilege
     * @return  bool
     */
    public function isAllowed(string $privilege='r'): bool
    {
        $acl   = null;
        $share = null;

        $this->_logger->debug('check acl for ['.$this->_id.'] with privilege ['.$privilege.']', [
            'category' => get_class($this),
        ]);
 
        if ($this->_user === null) {
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

        if ($priv === 'w+' && $this->isOwnerRequest()) {
            $result = true;
        } elseif ($this->isShared() || $this->isReference()) {
            if ($privilege === 'r' && ($priv === 'r' || $priv === 'w' || $priv === 'rw')) {
                $result = true;
            } elseif ($privilege === 'w' && ($priv === 'w' || $priv === 'rw')) {
                $result = true;
            }
        } else {
            if ($privilege === 'r' && ($priv === 'r' || $priv === 'rw')) {
                $result = true;
            } elseif ($privilege === 'w' && ($priv === 'w' || $priv === 'rw')) {
                $result = true;
            }
        }
        
        $this->_logger->debug('check acl for node ['.$this->_id.'], requested privilege ['.$privilege.']', [
            'category' => get_class($this),
            'params'   => ['privileges' => $priv],
        ]);
        
        return $result;
    }

    
    /**
     * Get privilege
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
        $acl    = [];
        $user   = $this->_user;

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
           
            if ((string)$share['owner'] === (string)$user->getId()) {
                return 'rw';
            }

            $acl = $share['acl'];
        } elseif ($this->isReference() && $this->isOwnerRequest()) {
            try {
                $share = $this->_fs->findRawNode($this->reference);
            } catch (\Exception $e) {
                $this->_logger->error('could not found share node ['.$this->shared.'] for reference ['.$this->_id.'], dead reference?', [
                    'category'  => get_class($this),
                    'exception' => $e,
                ]);

                $this->_forceDelete();
                return 'd';
            }
            
            if ($share['deleted'] instanceof UTCDateTime || $share['shared'] !== true) {
                $this->_logger->error('share node ['.$share['_id'].'] has been deleted, dead reference?', [
                    'category' => get_class($this),
                ]);

                $this->_forceDelete();
                return 'd';
            }
            
            $acl = $share['acl'];
        } elseif (!$this->isOwnerRequest()) {
            $this->_logger->warning("user [".$this->_user->getUsername()."] not allowed to access non owned node [".$this->_id."]", [
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
                if (array_key_exists('user', $rule) && $rule['user'] == $user->getUsername() && array_key_exists('priv', $rule)) {
                    $result = $rule['priv'];
                } elseif (array_key_exists('ldapdn', $rule) && $rule['ldapdn'] == $user->getLdapDn() && array_key_exists('priv', $rule)) {
                    $result = $rule['priv'];
                }

                if ($result == 'd') {
                    return $result;
                }
            }
        }
                
        if (array_key_exists('group', $acl)) {
            $groups = $user->getGroups();
            
            foreach ($acl['group'] as $rule) {
                if (array_key_exists('group', $rule) && in_array($rule['group'], $groups) && array_key_exists('priv', $rule)) {
                    $group_result = $rule['priv'];
                    
                    if ($group_result == 'd') {
                        return $group_result;
                    } elseif ($result === false) {
                        $result = $group_result;
                    } elseif ($result == 'r' && ($group_result == 'w' || $group_result == 'rw')) {
                        $result = $group_result;
                    } elseif ($group_result == 'rw') {
                        $result = $group_result;
                    }
                }
            }
        }
        
        if ($result === false) {
            return 'd';
        } else {
            return $result;
        }
    }
    

    /**
     * Get Attribute helper
     *
     * @param  array|string $attribute
     * @return array|string
     */
    protected function _getAttribute($attribute)
    {
        $requested = $attribute;
        $attribute = (array)$attribute;
        $metacheck = $attribute;
        $meta      = [];
        $clean     = [];

        foreach ($metacheck as $key => $attr) {
            if (substr($attr, 0, 5) == 'meta.') {
                $meta[] = substr($attr, 5);
            } else {
                $clean[] = $attr;
            }
        }
        
        if (!empty($meta)) {
            $clean[] = 'meta';
        }
        
        $attribute  = $clean;

        try {
            $sharenode  = $this->getShareNode();
        } catch (\Exception $e) {
            $sharenode  = null;
        }

        $build = [];

        foreach ($attribute as $key => $attr) {
            switch ($attr) {
                case 'id':
                    $build['id'] = (string)$this->_id;
                break;
                
                case 'name':
                case 'mime':
                    $build[$attr] = (string)$this->{$attr};
                break;
                
                case 'parent':
                    try {
                        $parent = $this->getParent();
                        if ($parent === null || $parent->getId() === null) {
                            $build[$attr] = null;
                        } else {
                            $build[$attr] = (string)$parent->getId();
                        }
                    } catch (\Exception $e) {
                        $build[$attr] = null;
                    }
                break;

                case 'meta':
                    $build['meta'] = (object)$this->getMetaAttribute($meta);
                break;
            
                case 'size':
                    $build['size'] = $this->getSize();
                break;
            
                case 'sharelink':
                    $build['sharelink'] = $this->isShareLink();
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

                case 'readonly':
                case 'directory':
                    $build[$attr] = $this->{$attr};
                break;

                case 'path':
                    try {
                        $build['path'] = $this->getPath();
                    } catch (\Balloon\Exception\NotFound $e) {
                        $build['path'] = null;
                    }
                break;

                case 'shared':
                    if ($this->directory === true) {
                        $build['shared'] = $this->isShared();
                    }
                break;
                
                case 'filtered':
                    if ($this->directory === true) {
                        $build['filtered'] = $this->isCustomFilter();
                    }
                break;
                
                case 'reference':
                    if ($this->directory === true) {
                        $build['reference'] = $this->isReference();
                    }
                break;

                case 'share':
                    if ($this->isSpecial() && $sharenode !== null) {
                        $build['share'] = $sharenode->getName();
                    } else {
                        $build['share'] = false;
                    }
                break;
 
                case 'access':
                    if ($this->isSpecial() && $sharenode !== null) {
                        $build['access'] = $sharenode->getAclPrivilege();
                    }
                break;
 
                case 'shareowner':
                    if ($this->isSpecial() && $sharenode !== null) {
                        $build['shareowner'] = (new User($this->_fs->findRawNode($this->getShareId())['owner'],
                          $this->_logger, $this->_fs)
                        )->getUsername();
                    }
                break;
            }
        }
            
        if (is_string($requested)) {
            if (array_key_exists($requested, $build)) {
                return $build[$requested];
            } else {
                return null;
            }
        }

        return $build;
    }


    /**
     * Duplicate name with a uniqid within name
     *
     * @param   string $name
     * @return  string
     */
    protected function _getDuplicateName(?string $name=null): string
    {
        if ($name === null) {
            $name = $this->name;
        }

        if ($this instanceof Collection) {
            return $name.' ('.substr(uniqid('', true), -4).')';
        } else {
            $ext  = substr(strrchr($name, '.'), 1);

            if ($ext === false) {
                return $name.' ('.substr(uniqid('', true), -4).')';
            } else {
                $name = substr($name, 0, -(strlen($ext) + 1));
                return $name.' ('.substr(uniqid('', true), -4).')'.'.'.$ext;
            }
        }
    }


    /**
     * Undelete
     *
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  bool
     */
    public function undelete(int $conflict=NodeInterface::CONFLICT_NOACTION, ?string $recursion=null, bool $recursion_first=true): bool
    {
        if (!$this->isAllowed('w')) {
            throw new Exception\Forbidden('not allowed to restore node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_UNDELETE
            );
        } elseif (!$this->isDeleted()) {
            throw new Exception\Conflict('node is not deleted, skip restore',
                Exception\Conflict::NOT_DELETED
            );
        }
        
        $parent = $this->getParent();
        if ($parent->isDeleted()) {
            throw new Exception\Conflict('could not restore node '.$this->name.' into a deleted parent',
                Exception\Conflict::DELETED_PARENT
            );
        }

        if ($parent->childExists($this->name)) {
            if ($conflict == NodeInterface::CONFLICT_MERGE) {
                $this->copyTo($parent, $conflict);
                $this->delete(true);
            } elseif ($conflict === NodeInterface::CONFLICT_RENAME) {
                $this->setName($this->_getDuplicateName());
                $this->raw_attributes['name'] = $this->name;
            } else {
                throw new Exception\Conflict('a node called '.$this->name.' does already exists in this collection',
                    Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
                );
            }
        }

        if ($recursion === null) {
            $recursion_first = true;
            $recursion = uniqid();
        } else {
            $recursion_first = false;
        }

        $this->deleted  = false;
        
        if ($this instanceof File) {
            $current = $this->version;
            $new = $this->increaseVersion();

            $this->history[] = [
                'version' => $new,
                'changed' => $this->changed,
                'user'    => $this->owner,
                'type'    => File::HISTORY_UNDELETE,
                'file'    => $this->file,
                'size'    => $this->size,
                'mime'    => $this->mime,
            ];
            
            return $this->save([
                'name',
                'deleted',
                'history',
                'version'
            ], [], $recursion, $recursion_first);
        } else {
            $this->save([
                'name',
                'deleted',
            ], [], $recursion, $recursion_first);
            
            return $this->doRecursiveAction('undelete', [
                    'conflict'        => $conflict,
                    'recursion'       => $recursion,
                    'recursion_first' => false
                ],
                NodeInterface::DELETED_ONLY
            );
        }

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
     * Share link
     *
     * @param   array $options
     * @return  bool
     */
    public function shareLink(array $options): bool
    {
        $valid = [
            'shared',
            'token',
            'password',
            'expiration',
        ];

        $set = [];
        foreach ($options as $option => $v) {
            if (!in_array($option, $valid, true)) {
                throw new Exception\InvalidArgument('share option '.$option.' is not valid');
            } else {
                $set[$option] = $v;
            }
        }

        if (!array_key_exists('token', $set)) {
            $set['token'] = uniqid((string)$this->_id, true);
        }

        if (array_key_exists('expiration', $set)) {
            if (empty($set['expiration'])) {
                unset($set['expiration']);
            } else {
                $set['expiration'] = (int)$set['expiration'];
            }
        }
        
        if (array_key_exists('password', $set)) {
            if (empty($set['password'])) {
                unset($set['password']);
            } else {
                $set['password'] = hash('sha256', $set['password']);
            }
        }
        
        $share = false;
        if (!array_key_exists('shared', $set)) {
            if (!is_array($this->sharelink)) {
                $share = true;
            }
        } else {
            if ($set['shared'] === 'true' || $set['shared'] === true) {
                $share = true;
            }

            unset($set['shared']);
        }
        
        if ($share === true) {
            $this->sharelink = $set;
            return $this->save(['sharelink']);
        } else {
            $this->sharelink = null;
            return $this->save([], ['sharelink']);
        }
    }


    /**
     * Get share options
     *
     * @return bool|array
     */
    public function getShareLink()
    {
        if (!$this->isShareLink()) {
            return false;
        } else {
            return $this->sharelink;
        }
    }


    /**
     * Get last modified timestamp
     *
     * @return int
     */
    public function getLastModified(): int
    {
        if ($this->changed instanceof UTCDateTime) {
            return (int)$this->changed->toDateTime()->format('U');
        } else {
            return 0;
        }
    }


    /**
     * Get unique id
     *
     * @return ObjectId|string
     */
    public function getId(bool $string=false)
    {
        if ($string === true) {
            return (string)$this->_id;
        } else {
            return $this->_id;
        }
    }


    /**
     * Get parent
     *
     * @return Collection
     */
    public function getParent(): ?Collection
    {
        try {
            if ($this->isRoot()) {
                return null;
            } elseif ($this->isInRoot()) {
                return $this->_fs->getRoot();
            } else {
                $parent = $this->_fs->findNodeWithId($this->parent);
                if ($parent->isShare() && !$parent->isOwnerRequest() && $this->_user !== null) {
                    $node = $this->_db->storage->findOne([
                        'owner' => $this->_user->getId(),
                        'shared' => true,
                        'reference' => $this->parent,
                    ]);
                    
                    return new Collection($node, $this->_fs);
                } else {
                    return $parent;
                }
            }
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound('parent node '.$this->parent.' could not be found',
                Exception\NotFound::PARENT_NOT_FOUND
            );
        }
    }


    /**
     * Get parents
     *
     * @param   array $parents
     * @return  array
     */
    public function getParents(?NodeInterface $node=null, array $parents=[]): array
    {
        if ($node === null) {
            $node = $this;
        }

        if ($node->isInRoot()) {
            return $parents;
        } else {
            $parent = $node->getParent();
            $parents[] = $parent;
            return $node->getParents($parent, $parents);
        }

        return $parents;
    }


    /**
     * Check if the node is a shared link
     *
     * @return bool
     */
    public function isShareLink(): bool
    {
        return is_array($this->sharelink) && $this->sharelink !== false;
    }

    
    /**
     * Download
     *
     * @return void
     */
    abstract public function get();


    /**
     * Get as zip
     *
     * @return void
     */
    public function getZip(): void
    {
        $temp = $this->_config->dir->temp.DIRECTORY_SEPARATOR.'zip';
        if (!file_exists($temp)) {
            mkdir($temp, 0700, true);
        }

        ZipStream::$temp = $temp;
        $archive = new ZipStream($this->name.".zip", "application/zip", $this->name.".zip");
        $this->zip($archive, false);
        $archive->finalize();
        exit();
    }


    /**
     * Create zip
     *
     * @param   ZipStream $archive
     * @param   bool $self true means that the zip represents the collection itself instead a child of the zip
     * @param   NodeInterface $parent
     * @param   string $path
     * @param   int $depth
     * @return  bool
     */
    public function zip(ZipStream $archive, bool $self=true, ?NodeInterface $parent=null, string $path='', int $depth=0): bool
    {
        if ($parent === null) {
            $parent = $this;
        }

        if ($parent instanceof Collection) {
            $children = $parent->getChildNodes();

            if ($self === true && $depth === 0) {
                $path = $parent->getName();
                $archive->addDirectory($path);
                $path .= DIRECTORY_SEPARATOR;
            } elseif ($depth === 0) {
                $path = '';
            } elseif ($depth !== 0) {
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
                        $this->_logger->error('failed add file ['.$child->getId().'] to zip stream', array(
                            'category' => get_class($this),
                            'exception' => $e,
                        ));
                    }
                }
            }
        } elseif ($parent instanceof File) {
            $archive->addFile($parent->get(), $parent->getName());
        }

        return true;
    }


    /**
     * Is reference
     *
     *  @return bool
     */
    public function isReference(): bool
    {
        return ($this->reference instanceof ObjectId);
    }


    /**
     * Set meta attribute
     *
     * @param   array|string
     * @param   mixed $value
     * @return  NodeInterface
     */
    public function setMetaAttribute($attributes, $value=null): NodeInterface
    {
        $this->meta = self::validateMetaAttribute($attributes, $value, $this->meta);
        $this->save('meta');
        return $this;
    }


    /**
     * validate meta attribut
     *
     * @param   array|string $attributes
     * @param   mixed $value
     * @param   array $set
     * @return  array
     */
    public static function validateMetaAttribute($attributes, $value=null, array $set=[]): array
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
     * Get meta attributes as array
     *
     * @param  string|array $attribute Specify attributes to return
     * @return string|array
     */
    public function getMetaAttribute($attribute=[])
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
     * Mark node as readonly
     *
     * @param   bool $readonly
     * @return  bool
     */
    public function setReadonly(bool $readonly=true): bool
    {
        $this->readonly = $readonly;
        return $this->save('readonly');
    }

    
    /**
     * Mark node as self-destroyable
     *
     * @param   UTCDateTime $ts
     * @return  bool
     */
    public function setDestroyable(?UTCDateTime $ts): bool
    {
        $this->destroy = $ts;
        
        if ($ts === null) {
            return $this->save([], 'destroy');
        } else {
            return $this->save('destroy');
        }
    }


    /**
     * Delete node
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     *
     * @param   bool $force
     * @param   bool $recursion_first
     * @param   string $recursion
     * @return  bool
     */
    abstract public function delete(bool $force=false, ?string $recursion=null, bool $recursion_first=true): bool;


    /**
     * Get original raw attributes before any processing
     *
     * @return array|\MongoDB\BSON\Document
     */
    public function getRawAttributes()
    {
        return $this->raw_attributes;
    }


    /**
     * Completly remove node
     *
     * @return bool
     */
    abstract protected function _forceDelete(): bool;


    /**
     * Check if node is in root
     *
     * @return bool
     */
    public function isInRoot(): bool
    {
        return $this->parent === null;
    }

    
    /**
     * Check if node is an instance of the actual root collection
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->_id === null && ($this instanceof Collection);
    }


    /**
     * Resolve node path
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
     * Save node attributes
     *
     * @param  string|array $attributes
     * @param  string|array $remove
     * @param  string $recursion
     * @param  bool $recursion_first
     * @return bool
     */
    public function save($attributes=[], $remove=[], ?string $recursion=null, bool $recursion_first=true): bool
    {
        if (!$this->isAllowed('w') && !$this->isReference()) {
            throw new Exception\Forbidden('not allowed to modify node '.$this->name,
                Exception\Forbidden::NOT_ALLOWED_TO_MODIFY
            );
        }
        
        $remove     = (array)$remove;
        $attributes = (array)$attributes;
        $this->_hook->run('preSaveNodeAttributes',
            [$this, &$attributes, &$remove, &$recursion, &$recursion_first]);

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
            } else {
                $result =$this->_db->storage->updateOne([
                    '_id' => $this->_id,
                ], $update);
            }
            
            $this->_hook->run('postSaveNodeAttributes',
                [$this, $attributes, $remove, $recursion, $recursion_first]);
            
            $this->_logger->info('modified node attributes of ['.$this->_id.']', [
                'category' => get_class($this),
                'params'   => $update,
            ]);
       
            return true;
        } catch (\Exception $e) {
            $this->_logger->error('failed modify node attributes of ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e
            ]);

            throw $e;
        }
    }


    /**
     * Get children
     *
     * @param   array $filter
     * @param   array $attributes
     * @param   int $limit
     * @param   string $cursor
     * @param   bool $has_more
     * @return  array
     */
    public static function loadNodeAttributesWithCustomFilter(
        ?array $filter = null,
        array $attributes = ['_id'],
        ?int $limit = null,
        ?int &$cursor = null,
        ?bool &$has_more = null)
    {
        $default = [
            '_id'       => 1,
            'directory' => 1,
            'shared'    => 1,
            'name'      => 1,
            'parent'    => 1,
        ];

        $search_attributes = array_merge($default, array_fill_keys($attributes, 1));
        $list   = [];
        $result =$this->_db->storage->find($filter, [
            'skip'      => $cursor,
            'limit'     => $limit,
            'projection'=> $search_attributes
        ]);

        $cursor += $limit;

        $result = $result->toArray();
        $count  = count($result);
        
        if ($cursor > $count) {
            $cursor = $count;
        }
        
        $has_more = ($cursor < $count);
        
        foreach ($result as $node) {
            if ($node['directory'] === true) {
                $node = new Collection($node);
            } else {
                $node = new File($node);
            }

            $values = $node->getAttribute($attributes);
            $list[] = $values;
        }

        return $list;
    }
}
