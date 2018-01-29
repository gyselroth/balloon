<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\Collection;
use Balloon\Server;
use Micro\Auth\Identity;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class User implements RoleInterface
{
    /**
     * User unique id.
     *
     * @var ObjectId
     */
    protected $_id;

    /**
     * Username.
     *
     * @var string
     */
    protected $username;

    /**
     * Firstname.
     *
     * @var string
     */
    protected $firstname;

    /**
     * Lastname.
     *
     * @var string
     */
    protected $lastname;

    /**
     * Locale.
     *
     * @var string
     */
    protected $locale = 'en_US';

    /**
     * Groups.
     *
     * @var array
     */
    protected $groups = [];

    /**
     * Last sync timestamp.
     *
     * @var UTCDateTime
     */
    protected $last_attr_sync;

    /**
     * Soft Quota.
     *
     * @var int
     */
    protected $soft_quota = -1;

    /**
     * Hard Quota.
     *
     * @var int
     */
    protected $hard_quota = -1;

    /**
     * Is user deleted?
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * Admin.
     *
     * @var bool
     */
    protected $admin = false;

    /**
     * Created.
     *
     * @var UTCDateTime
     */
    protected $created;

    /**
     * Changed.
     *
     * @var UTCDateTime
     */
    protected $changed;

    /**
     * avatar.
     *
     * @var Binary
     */
    protected $avatar;

    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Mail.
     *
     * @var string
     */
    protected $mail;

    /**
     * Db.
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
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Password.
     *
     * @var string
     */
    protected $password;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Instance user.
     *
     * @param array           $attributes
     * @param Server          $server
     * @param Database        $db
     * @param LoggerInterface $logger
     */
    public function __construct(array $attributes, Server $server, Database $db, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->db = $db;
        $this->logger = $logger;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }
    }

    /**
     * Return username as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->username;
    }

    /**
     * Update user with identity attributes.
     *
     * @param Identity $identity
     *
     * @return User
     */
    public function updateIdentity(Identity $identity): self
    {
        $attr_sync = $identity->getAdapter()->getAttributeSyncCache();
        if ($attr_sync === -1) {
            return $this;
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

            $this->save($save);

            return $this;
        }

        $this->logger->debug('user auth attribute sync cache is in time', [
            'category' => get_class($this),
        ]);

        return $this;
    }

    /**
     * Set user attributes.
     *
     * @param array $attributes
     *
     * @return bool
     */
    public function setAttributes(array $attributes = []): bool
    {
        $attributes = $this->server->validateUserAttributes($attributes);

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        return $this->save(array_keys($attributes));
    }

    /**
     * Get Attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return [
            'id' => $this->_id,
            'username' => $this->username,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'locale' => $this->locale,
            'namespace' => $this->namespace,
            'created' => $this->created,
            'changed' => $this->changed,
            'deleted' => $this->deleted,
            'soft_quota' => $this->soft_quota,
            'hard_quota' => $this->hard_quota,
            'mail' => $this->mail,
            'avatar' => $this->avatar,
        ];
    }

    /**
     * Find all shares with membership.
     *
     * @param bool $string
     *
     * @return array
     */
    public function getShares(bool $string = false): array
    {
        $item = $this->db->storage->find([
            'deleted' => false,
            'shared' => true,
            'owner' => $this->_id,
        ], [
            '_id' => 1,
            'reference' => 1,
        ]);

        $found = [];

        foreach ($item as $child) {
            if (isset($child['reference']) && $child['reference'] instanceof ObjectId) {
                $share = $child['reference'];
            } else {
                $share = $child['_id'];
            }

            if (true === $string) {
                $found[] = (string) $share;
            } else {
                $found[] = $share;
            }
        }

        return $found;
    }

    /**
     * Get node attribute usage.
     *
     * @param array|string $attributes
     * @param int          $limit
     *
     * @return array
     */
    public function getNodeAttributeSummary($attributes = [], int $limit = 25): array
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
     * Get filesystem.
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
     * Check if user has share.
     *
     * @param Collection $node
     *
     * @return bool
     */
    public function hasShare(Collection $node): bool
    {
        $result = $this->db->storage->count([
            'reference' => $node->getId(),
            'directory' => true,
            'owner' => $this->_id,
        ]);

        return 1 === $result;
    }

    /**
     * Find new shares and create reference.
     *
     * @return User
     */
    public function updateShares(): self
    {
        $item = $this->db->storage->find([
            'deleted' => false,
            'shared' => true,
            'directory' => true,
            '$or' => [
                ['acl' => [
                    '$elemMatch' => [
                        'id' => (string) $this->_id,
                        'type' => 'user',
                    ],
                ]],
                ['acl' => [
                    '$elemMatch' => [
                        'id' => ['$in' => array_map('strval', $this->groups)],
                        'type' => 'group',
                    ],
                ]],
            ],
        ]);

        $found = [];
        $list = [];
        foreach ($item as $child) {
            $found[] = $child['_id'];
            $list[(string) $child['_id']] = $child;
        }

        if (empty($found)) {
            return $this;
        }

        //check for references
        $item = $this->db->storage->find([
            'directory' => true,
            'shared' => true,
            'owner' => $this->_id,
            'reference' => ['$exists' => 1],
        ]);

        $exists = [];
        foreach ($item as $child) {
            if (!in_array($child['reference'], $found)) {
                $this->logger->debug('found dead reference ['.$child['_id'].'] pointing to share ['.$child['reference'].']', [
                    'category' => get_class($this),
                ]);

                try {
                    $this->getFilesystem()->findNodeById($child['_id'])->delete(true);
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
            $node = $list[(string) $add];

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
                'shared' => true,
                'parent' => null,
                'reference' => $node['_id'],
            ];

            $dir = $this->getFilesystem()->getRoot();

            try {
                $dir->addDirectory($node['share_name'], $attrs);
            } catch (Exception\Conflict $e) {
                $new = $node['share_name'].' ('.substr(uniqid('', true), -4).')';
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

        return $this;
    }

    /**
     * Get unique id.
     *
     * @return ObjectId
     */
    public function getId(): ObjectId
    {
        return $this->_id;
    }

    /**
     * Get namespace.
     *
     * @return string
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Get hard quota.
     *
     * @return int
     */
    public function getHardQuota(): int
    {
        return $this->hard_quota;
    }

    /**
     * Set hard quota.
     *
     * @param int $quota In Bytes
     *
     * @return User
     */
    public function setHardQuota(int $quota): self
    {
        $this->hard_quota = (int) $quota;
        $this->save(['hard_quota']);

        return $this;
    }

    /**
     * Set soft quota.
     *
     * @param int $quota In Bytes
     *
     * @return User
     */
    public function setSoftQuota(int $quota): self
    {
        $this->soft_quota = (int) $quota;
        $this->save(['soft_quota']);

        return $this;
    }

    /**
     * Save.
     *
     * @param array $attributes
     *
     * @return bool
     */
    public function save(array $attributes = []): bool
    {
        $this->changed = new UTCDateTime();
        $attributes[] = 'changed';

        $set = [];
        foreach ($attributes as $attr) {
            $set[$attr] = $this->{$attr};
        }

        $result = $this->db->user->updateOne([
            '_id' => $this->_id,
        ], [
            '$set' => $set,
        ]);

        return true;
    }

    /**
     * Get used qota.
     *
     * @return array
     */
    public function getQuotaUsage(): array
    {
        $result = $this->db->storage->find(
            [
                'owner' => $this->_id,
                'directory' => false,
                'deleted' => false,
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
            'used' => $sum,
            'available' => ($this->hard_quota - $sum),
            'hard_quota' => $this->hard_quota,
            'soft_quota' => $this->soft_quota,
        ];
    }

    /**
     * Check quota.
     *
     * @param int $add Size in bytes
     *
     * @return bool
     */
    public function checkQuota(int $add): bool
    {
        if ($this->hard_quota === -1) {
            return true;
        }

        $quota = $this->getQuotaUsage();

        if (($quota['used'] + $add) > $quota['hard_quota']) {
            return false;
        }

        return true;
    }

    /**
     * Delete user.
     *
     * @param bool $force
     *
     * @return bool
     */
    public function delete(bool $force = false): bool
    {
        if (false === $force) {
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
     * Undelete user.
     *
     * @return bool
     */
    public function undelete(): bool
    {
        $this->deleted = false;

        return $this->save(['deleted']);
    }

    /**
     * Check if user is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Get Username.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get groups.
     *
     * @return array
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Get attribute usage summary.
     *
     * @param string $attribute
     * @param string $type
     * @param int    $limit
     *
     * @return array
     */
    protected function _getAttributeSummary(string $attribute, string $type = 'string', int $limit = 25): array
    {
        $mongodb = $this->db->storage;

        $ops = [
            [
                '$match' => [
                    '$and' => [
                        ['owner' => $this->_id],
                        ['deleted' => false],
                        [$attribute => ['$exists' => true]],
                    ],
                ],
            ],
        ];

        if ('array' === $type) {
            $ops[] = [
                '$unwind' => '$'.$attribute,
            ];
        }

        $ops[] = [
            '$group' => [
                '_id' => '$'.$attribute,
                'sum' => ['$sum' => 1],
            ],
        ];

        $ops[] = [
            '$sort' => [
               'sum' => -1,
               '_id' => 1,
            ],
        ];

        $ops[] = [
            '$limit' => $limit,
        ];

        return $mongodb->aggregate($ops)->toArray();
    }
}
