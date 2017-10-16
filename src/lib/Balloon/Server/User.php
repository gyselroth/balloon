<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use \Balloon\Filesystem;
use \Balloon\Exception;
use \Balloon\Filesystem\Node\Collection;
use \Micro\Auth\Identity;
use \MongoDB\BSON\ObjectID;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\BSON\Binary;
use \Psr\Log\LoggerInterface;
use \Balloon\Helper;
use \Balloon\Server;

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
     * @var Binary
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
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Server
     *
     * @var Server
     */
    protected $server;


    /**
     * Filesystem
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Valid attributes
     *
     * @var array
     */
     static protected $valid_attributes = [
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
         'admin'
     ];


    /**
     * Instance user
     *
     * @param   array $attributes
     * @param   Server $server
     * @param   bool $ignore_deleted
     * @throws  Exception\InvalidArgument if a given argument is not valid
     * @throws  Exception\NotAuthenticated if user is deleted
     * @return  void
     */
    public function __construct(array $attributes, Server $server, LoggerInterface $logger, bool $ignore_deleted=true)
    {
        $this->server   = $server;
        $this->db       = $server->getDatabase();
        $this->logger   = $logger;

        foreach ($attributes as $attr => $value) {
            if ($attr !== '_id' && !in_array($attr, self::$valid_attributes)) {
                throw new Exception\InvalidArgument('requested attribute '.$attr.' does not exists');
            }
            $this->{$attr} = $value;
        }

        if ($this->deleted === true && $ignore_deleted === false) {
            throw new Exception\NotAuthenticated(
                'user '.$username.' is deleted',
                Exception\NotAuthenticated::USER_DELETED
            );
        }
    }


    /**
     * Update user with identity attributes
     *
     * @param  Identity $identity
     * @return bool
     */
    public function updateIdentity(Identity $identity): bool
    {
        $attr_sync = $identity->getAdapter()->getAttributeSyncCache();
        if ($attr_sync == -1) {
            return true;
        }

        $cache = ($this->last_attr_sync instanceof UTCDateTime ?
            $this->last_attr_sync->toDateTime()->format('U') : 0);

        if (time() - $attr_sync > $cache) {
            $this->logger->info('user attribute sync cache time expired, resync with auth attributes', [
                'category' => get_class($this),
            ]);

            $attributes = $identity->getAttributes();
            foreach ($attributes as $attr => $value) {
                $this->{$attr} = $value;
            }

            $this->last_attr_sync = new UTCDateTime();

            $save = array_keys($attributes);
            $save[] = 'last_attr_sync';
            return $this->save($save);
        } else {
            $this->logger->debug('user auth attribute sync cache is in time', [
                'category' => get_class($this),
            ]);
            return true;
        }
    }


    /**
     * Set user attribute
     *
     * @param  array $attribute
     */
    public function setAttribute($attribute=[])
    {
        foreach ($attribute as $attr => $value) {
            if (!in_array($attr, self::$valid_attributes)) {
                throw new Exception\InvalidArgument('requested attribute '.$attr.' does not exists');
            }

            switch ($attr) {
                case 'id':
                    $this->_id = (string)$value;
                break;

                case 'avatar':
                    $this->avatar = new Binary(base64_decode($value));
                break;

                case 'created':
                case 'last_attr_sync':
                    $this->{$attr} = Helper::DateTimeToUnix($value);
                break;

                default:
                    $this->{$attr} = $value;
                break;
            }
        }

        return $this;
    }


    /**
     * Get user attribute
     *
     * @param  string|array $attribute
     * @return mixed
     */
    public function getAttribute($attribute=null)
    {
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
            if (!in_array($attr, self::$valid_attributes)) {
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
     * Get filesystem
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        if ($this->fs instanceof Filesystem) {
            return $this->fs;
        }

        return $this->fs = $this->server->getFilesystem($this);
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
                    $this->getFilesystem()->findNodeWithId($child['_id'])->delete(true);
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
                $dir = $this->getFilesystem()->getRoot();
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
     * @throws  Exception\InvalidArgument if a given argument is not valid
     * @return  bool
     */
    public function save(array $attributes=[]): bool
    {
        $set = [];
        foreach ($attributes as $attr) {
            if (!in_array($attr, self::$valid_attributes)) {
                throw new Exception\InvalidArgument('attribute '.$attr.' is not valid');
            }
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
        $result = $this->db->storage->find(
            [
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
            $result_data = $this->getFilesystem()->getRoot()->delete();
            $this->deleted = true;
            $result_user = $this->save(['deleted']);
        } else {
            $result_data = $this->getFilesystem()->getRoot()->delete(true);

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
     * Get groups
     *
     * @return array
     */
    public function getGroups(): array
    {
        return $this->groups;
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
