<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \Balloon\Filesystem;
use \Balloon\Exception;
use \Balloon\Filesystem\Node\Collection;
use \Micro\Auth\Identity;
use \MongoDB\BSON\ObjectID;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\BSON\Binary;
use \Psr\Log\LoggerInterface as Logger;

class User
{
    /**
     * User unique id
     *
     * @var ObjectID
     */
    protected $_id;


    /**
     * Username
     *
     * @var string
     */
    protected $username;

    
    /**
     * LDAP DN
     *
     * @var string
     */
    protected $ldapdn;


    /**
     * Groups
     *
     * @var array
     */
    protected $groups = [];

        
    /**
     * Last sync timestamp
     *
     * @var UTCDateTime
     */
    protected $last_attr_sync;


    /**
     * Soft Quota
     *
     * @var int
     */
    protected $soft_quota = 0;

    
    /**
     * Hard Quota
     *
     * @var int
     */
    protected $hard_quota = 0;
    

    /**
     * Is user deleted?
     *
     * @var bool
     */
    protected $deleted = false;


    /**
     * Auth instance
     *
     * @var Auth
     */
    protected $auth;

    
    /**
     * Admin
     *
     * @var bool
     */
    protected $admin = false;

    
    /**
     * Created
     *
     * @var UTCDateTime
     */
    protected $created;


    /**
     * avatar
     *
     * @var \MongoBinData
     */
    protected $avatar;


    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace;
    
    
    /**
     * Mail
     *
     * @var string
     */
    protected $mail;
    

    /**
     * Db
     *
     * @var Database
     */
    protected $db;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;
    

    /**
     * Filesystem
     *
     * @var Filesystem
     */
    protected $fs;


    /**
     * Load user object with name or with id
     *
     * @param   string|ObjectID|Auth $user
     * @param   Logger $logger
     * @param   Filesystem $fs
     * @param   bool $autocreate
     * @param   bool $ignore_deleted
     * @return  void
     */
    public function __construct($user, Logger $logger, Filesystem $fs, bool $autocreate=false, bool $ignore_deleted=true)
    {
        $this->fs       = $fs;
        $this->db       = $fs->getDatabase();
        $this->logger   = $logger;

        if ($user instanceof ObjectID) {
            $attributes = $this->db->user->findOne([
                '_id' => $user
            ]);
            
            if ($attributes === false || $attributes === null) {
                throw new Exception('user with id ' . $user . ' does not exists');
            }

            $username = $user;
        } else {
            if ($user instanceof Identity) {
                $this->auth = $user;
                $username   = $this->auth->getIdentifier();
            } else {
                $username = $user;
            }

            $attributes = $this->db->user->findOne([
                'username' => $username
            ]);
        }

        if (is_object($attributes)) {
            $attributes = Helper::convertBSONDocToPhp($attributes);
        }
        
        $this->fs->getHook()->run('preInstanceUser', [$this, &$username, &$attributes, $autocreate]);
            
        if ($attributes === false || $attributes === null) {
            throw new Exception('user was not found');
        }

        $logparams = (array)$attributes;
        if (isset($logparams['avatar']) && $logparams['avatar'] instanceof Binary) {
            $logparams['avatar'] = '<bin>';
        }

        $this->logger->info('select user ['.$username.'] attributes from mongodb', [
            'category' => get_class($this),
            'params'   => $logparams,
        ]);

        //set user properties
        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        if ($this->deleted === true && $ignore_deleted === false) {
            throw new Exception\NotAuthenticated('user '.$username.' is deleted',
                Exception\NotAuthenticated::USER_DELETED
            );
        }

        if ($user instanceof Auth) {
            $attr_sync = $user->getAttributeSyncCache();
            if ($attr_sync == -1) {
                return;
            }

            $cache = ($this->last_attr_sync instanceof UTCDateTime ?
            $this->last_attr_sync->toDateTime()->format('U') : 0);
        
            if (time() - $attr_sync > $cache) {
                $this->logger->info('user attribute sync cache time expired, resync with auth attributes', [
                    'category' => get_class($this),
                ]);

                $this->syncUser();
            } else {
                $this->logger->debug('user auth attribute sync cache is in time', [
                    'category' => get_class($this),
                ]);
            }
        }
        
        $this->fs->getHook()->run('postInstanceUser', [$this]);
    }


    /**
     * Get user attribute
     *
     * @param  string|array $attribute
     * @return mixed
     */
    public function getAttribute($attribute=null)
    {
        $valid = [
            'id',
            'username',
            'created',
            'soft_quota',
            'hard_quota',
            'mail',
            'namespace',
            'last_attr_sync',
            'avatar',
            'created',
        ];

        $default = [
            'id',
            'username',
            'namespace',
            'created',
            'soft_quota',
            'hard_quota',
            'mail',
        ];

        if (empty($attribute)) {
            $requested = $default;
        } elseif (is_string($attribute)) {
            $requested = (array)$attribute;
        } elseif (is_array($attribute)) {
            $requested = $attribute;
        }
        
        $resolved = [];
        foreach ($requested as $attr) {
            if (!in_array($attr, $valid)) {
                throw new Exception\InvalidArgument('requested attribute '.$attr.' does not exists');
            }

            switch ($attr) {
                case 'id':
                    $resolved['id'] = (string)$this->_id;
                break;
                
                case 'avatar':
                    if ($this->avatar instanceof Binary) {
                        $resolved['avatar'] = base64_encode($this->avatar->getData());
                    }
                break;

                case 'created':
                case 'last_attr_sync':
                    $resolved[$attr] = Helper::DateTimeToUnix($this->{$attr});
                break;

                default:
                    $resolved[$attr] = $this->{$attr};
                break;
            }
        }

        if (is_string($attribute)) {
            return $resolved[$attribute];
        } else {
            return $resolved;
        }
    }

    
    /**
     * Find all shares with membership
     *
     * @param  bool $string
     * @return array
     */
    public function getShares(bool $string=false): array
    {
        $item = $this->db->storage->find([
            'deleted'   => false,
            'shared'    => true,
            'owner'     => $this->_id,
        ], [
            '_id' => 1,
            'reference' => 1,
        ]);

        $found  = [];
        
        foreach ($item as $child) {
            if (isset($child['reference']) && $child['reference'] instanceof ObjectID) {
                $share = $child['reference'];
            } else {
                $share = $child['_id'];
            }

            if ($string === true) {
                $found[] = (string)$share;
            } else {
                $found[] = $share;
            }
        }
        
        return $found;
    }

    /**
     * Get node attribute usage
     *
     * @param   string|array $attributes
     * @param   int $limit
     * @return  array
     */
    public function getNodeAttributeSummary($attributes=[], int $limit=25): array
    {
        $mongodb = $this->db->storage;

        $valid = [
            'mime' => 'string',
            'meta.tags' => 'array',
            'meta.author' => 'string',
            'meta.color' => 'string',
            'meta.license' => 'string',
            'meta.copyright' => 'string',
        ];

        if (empty($attributes)) {
            $attributes = array_keys($valid);
        } elseif (is_string($attributes)) {
            $attributes = [$attributes];
        }

        $filter = array_intersect_key($valid, array_flip($attributes));
        $result = [];

        foreach ($filter as $attribute => $type) {
            $result[$attribute] = $this->_getAttributeSummary($attribute, $type, $limit);
        }

        return $result;
    }


    /**
     * Get attribute usage summary
     *
     * @param   string $attribute
     * @param   string $type
     * @param   int $limit
     * @return  array
     */
    protected function _getAttributeSummary(string $attribute, string $type='string', int $limit=25): array
    {
        $mongodb = $this->db->storage;

        $ops = [
            [
                '$match' => [
                    '$and' => [
                        ['owner' => $this->_id],
                        ['deleted' => false],
                        [$attribute => ['$exists' => true] ]
                    ]
                ]
            ],
        ];
        
        if ($type === 'array') {
            $ops[] = [
                '$unwind' => '$'.$attribute
            ];
        }
        
        $ops[] = [
            '$group' => [
                "_id" => '$'.$attribute,
                "sum" => ['$sum' => 1],
            ],
        ];
        
        $ops[] = [
            '$sort' => [
               "sum" => -1,
               "_id" => 1
            ],
        ];
        
        
        $ops[] = [
            '$limit' => $limit
        ];
        
        return $mongodb->aggregate($ops)->toArray();
    }
    

    /**
     * Get fs
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->fs;
    }


    /**
     * Is Admin user?
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->admin;
    }

    
    /**
     * Check if user has share
     *
     * @param   Collection $node
     * @return  bool
     */
    public function hasShare(Collection $node): bool
    {
        $result = $this->db->storage->count([
            'reference' => $node->getId(),
            'directory' => true,
            'owner'     => $this->_id
        ]);

        return $result === 1;
    }
    

    /**
     * Find new shares and create reference
     *
     * @return bool
     */
    public function findNewShares(): bool
    {
        $item = $this->db->storage->find([
            'deleted'   => false,
            'shared'    => true,
            'directory' => true,
            '$or' => [
                ['acl.user'  => [
                    '$elemMatch' => [
                        'user' => $this->username,
                    ]
                ]],
                ['acl.group'  => [
                    '$elemMatch' => [
                        'group' => [
                            '$in' => $this->groups,
                        ]
                    ]
                ]],
            ]
        ]);

        $found  = [];
        $list   = [];
        foreach ($item as $child) {
            $found[] = $child['_id'];
            $list[(string)$child['_id']] = $child;
        }
        
        if (empty($found)) {
            return false;
        }

        //check for references
        $item = $this->db->storage->find([
            'directory' => true,
            'shared'    => true,
            'owner'     => $this->_id,
            'reference' => ['$exists' => 1]
            //    '$in' => $found
            //]
        ]);

        $exists = [];
        foreach ($item as $child) {
            if (!in_array($child['reference'], $found)) {
                $this->logger->debug('found dead reference ['.$child['_id'].'] pointing to share ['.$child['reference'].']', [
                    'category' => get_class($this),
                ]);

                try {
                    $this->fs->findNodeWithId($child['_id'])->delete(true);
                } catch (\Exception $e) {
                }
            } else {
                $this->logger->debug('found existing share reference ['.$child['_id'].'] pointing to share ['.$child['reference'].']', [
                    'category' => get_class($this),
                ]);

                $exists[] = $child['reference'];
            }
        }
        
        $new = array_diff($found, $exists);
        
        foreach ($new as $add) {
            $node = $list[(string)$add];
                
            $this->logger->info('found new share ['.$node['_id'].']', [
                'category' => get_class($this),
            ]);
            
            if ($node['owner'] == $this->_id) {
                $this->logger->debug('skip creating reference to share ['.$node['_id'].'] cause share owner ['.$node['owner'].'] is the current user', [
                    'category' => get_class($this),
                ]);

                continue;
            }

            $attrs = [
                'shared'    => true,
                'parent'    => null,
                'reference' => $node['_id']
            ];
            
            try {
                $dir = $this->fs->getRoot();
                $dir->addDirectory($node['name'], $attrs);
            } catch (Exception\Conflict $e) {
                $new = $node['name'].' ('.substr(uniqid('', true), -4).')';
                $dir->addDirectory($new, $attrs);
            } catch (\Exception $e) {
                $this->logger->error('failed create new share reference to share ['.$node['_id'].']', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
                
                throw $e;
            }
              
            $this->logger->info('created new share reference to share ['.$node['_id'].']', [
                'category' => get_class($this),
            ]);
        }
        
        return true;
    }


    /**
     * Sync user
     *
     * @return bool
     */
    public function syncUser(): bool
    {
        $attributes = $this->auth->getAttributes();
        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }
        
        $this->last_attr_sync = new UTCDateTime();
        
        $save = array_keys($attributes);
        $save[] = 'last_attr_sync';
        return $this->save($save);
    }


    /**
     * Get unique id
     *
     * @return ObjectID
     */
    public function getId(): ObjectID
    {
        return $this->_id;
    }


    /**
     * Get hard quota
     *
     * @return int
     */
    public function getHardQuota(): int
    {
        return $this->hard_quota;
    }

    
    /**
     * Set hard quota
     *
     * @param   int $quota In Bytes
     * @return  User
     */
    public function setHardQuota(int $quota): User
    {
        $this->hard_quota = (int)$quota;
        $this->save(['hard_quota']);
        return $this;
    }

    
    /**
     * Set soft quota
     *
     * @param   int $quota In Bytes
     * @return  User
     */
    public function setSoftQuota(int $quota): User
    {
        $this->soft_quota = (int)$quota;
        $this->save(['soft_quota']);
        return $this;
    }
    

    /**
     * Save
     *
     * @param   array $attributes
     * @return  bool
     */
    public function save(array $attributes=[]): bool
    {
        $set = [];
        foreach ($attributes as $attr) {
            $set[$attr] = $this->{$attr};
        }

        $result = $this->db->user->updateOne([
            '_id' => $this->_id,
        ], [
            '$set' => $set
        ]);
        
        return true;
    }


    /**
     * Get used qota
     *
     * @return array
     */
    public function getQuotaUsage(): array
    {
        $result = $this->db->storage->find([
                'owner'     => $this->_id,
                'directory' => false,
                'deleted'   => false,
            ],
            ['size']
        );

        $sum = 0;
        foreach ($result as $size) {
            if (isset($size['size'])) {
                $sum += $size['size'];
            }
        }

        return [
            'used'        => $sum,
            'available'   => ($this->hard_quota - $sum),
            'hard_quota'  => $this->hard_quota,
            'soft_quota'  => $this->soft_quota,
        ];
    }


    /**
     * Check quota
     *
     * @param   int $add Size in bytes
     * @return  bool
     */
    public function checkQuota(int $add): bool
    {
        $quota = $this->getQuotaUsage();

        if (($quota['used']+$add) > $quota['hard_quota']) {
            return false;
        }

        return true;
    }


    /**
     * Delete user
     *
     * @param  bool $force
     * @return bool
     */
    public function delete(bool $force=false): bool
    {
        if ($force === false) {
            $result_data = $this->fs->getRoot()->delete();
            $this->deleted = true;
            $result_user = $this->save(['deleted']);
        } else {
            $result_data = $this->fs->getRoot()->delete(true);
            
            $result = $this->db->user->deleteOne([
                '_id' => $this->_id,
            ]);
            $result_user = $result->isAcknowledged();
        }
        
        return $result_data && $result_user;
    }


    /**
     * Undelete user
     *
     * @return bool
     */
    public function undelete(): bool
    {
        $this->deleted = false;
        return $this->save(['deleted']);
    }

    
    /**
     * Check if user is deleted
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }


    /**
     * Get Username
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }
    
    
    /**
     * Get LDAP DN
     *
     * @return string
     */
    public function getLdapDn(): string
    {
        return $this->ldapdn;
    }


    /**
     * Get groups
     *
     * @return array
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
    
    
    /**
     * Get auth
     *
     * @return Auth
     */
    public function getAuth(): Auth
    {
        return $this->auth;
    }
    
    
    /**
     * Return username as string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->username;
    }
}
