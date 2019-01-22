<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\Filesystem;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Server;
use Generator;
use Micro\Auth\Identity;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
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
     * Optional user attributes.
     *
     * @var array
     */
    protected $optional = [];

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
     * @var bool|UTCDateTime
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
     */
    public function __toString(): string
    {
        return $this->username;
    }

    /**
     * Update user with identity attributes.
     *
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
     */
    public function getAttributes(): array
    {
        return [
            '_id' => $this->_id,
            'username' => $this->username,
            'locale' => $this->locale,
            'namespace' => $this->namespace,
            'created' => $this->created,
            'changed' => $this->changed,
            'deleted' => $this->deleted,
            'soft_quota' => $this->soft_quota,
            'hard_quota' => $this->hard_quota,
            'mail' => $this->mail,
            'admin' => $this->admin,
            'optional' => $this->optional,
            'avatar' => $this->avatar,
        ];
    }

    /**
     * Find all shares with membership.
     */
    public function getShares(): array
    {
        $result = $this->getFilesystem()->findNodesByFilter([
            'deleted' => false,
            'shared' => true,
            'owner' => $this->_id,
        ]);

        $list = [];
        foreach ($result as $node) {
            $list[] = $node->getShareId();
        }

        return $list;
    }

    /**
     * Get node attribute usage.
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
     */
    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /**
     * Check if user has share.
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
            $lookup = array_column($node['acl'], null, 'privilege');
            if (isset($lookup['d'])) {
                $this->logger->debug('ignore share ['.$node['_id'].'] with deny privilege', [
                    'category' => get_class($this),
                ]);

                continue;
            }

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
                'pointer' => $node['_id'],
            ];

            $dir = $this->getFilesystem()->getRoot();

            try {
                $dir->addDirectory($node['share_name'], $attrs);
            } catch (Exception\Conflict $e) {
                $conflict_node = $dir->getChild($node['share_name']);

                if (!$conflict_node->isReference() && $conflict_node->getShareId() != $attrs['reference']) {
                    $new = $node['share_name'].' ('.substr(uniqid('', true), -4).')';
                    $dir->addDirectory($new, $attrs);
                }
            } catch (BulkWriteException $e) {
                if ($e->getCode() !== 11000) {
                    throw $e;
                }

                $this->logger->warning('share reference to ['.$node['_id'].'] has already been created', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                continue;
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
     */
    public function getId(): ObjectId
    {
        return $this->_id;
    }

    /**
     * Get namespace.
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Get hard quota.
     */
    public function getHardQuota(): int
    {
        return $this->hard_quota;
    }

    /**
     * Set hard quota.
     */
    public function setHardQuota(int $quota): self
    {
        $this->hard_quota = (int) $quota;
        $this->save(['hard_quota']);

        return $this;
    }

    /**
     * Set soft quota.
     */
    public function setSoftQuota(int $quota): self
    {
        $this->soft_quota = (int) $quota;
        $this->save(['soft_quota']);

        return $this;
    }

    /**
     * Save.
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
     */
    public function getQuotaUsage(): array
    {
        $result = $this->db->storage->aggregate([
            [
                '$match' => [
                    'owner' => $this->_id,
                    'directory' => false,
                    'deleted' => false,
                    'storage_reference' => null,
                ],
            ],
            [
                '$group' => [
                    '_id' => null,
                    'sum' => ['$sum' => '$size'],
                ],
            ],
        ]);

        $result = iterator_to_array($result);
        $sum = 0;
        if (isset($result[0]['sum'])) {
            $sum = $result[0]['sum'];
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
     */
    public function delete(bool $force = false, bool $data = false, bool $force_data = false): bool
    {
        if (false === $force) {
            $this->deleted = new UTCDateTime();
            $result = $this->save(['deleted']);
        } else {
            $result = $this->db->user->deleteOne([
                '_id' => $this->_id,
            ]);

            $result = $result->isAcknowledged();
        }

        if ($data === true) {
            $this->getFilesystem()->getRoot()->delete($force_data);
        }

        return $result;
    }

    /**
     * Undelete user.
     */
    public function undelete(): bool
    {
        $this->deleted = false;

        return $this->save(['deleted']);
    }

    /**
     * Check if user is deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deleted instanceof UTCDateTime;
    }

    /**
     * Get Username.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get groups.
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Get resolved groups.
     *
     * @return Generator
     */
    public function getResolvedGroups(?int $offset = null, ?int $limit = null): ?Generator
    {
        return $this->server->getGroups([
            '_id' => ['$in' => $this->groups],
        ], $offset, $limit);
    }

    /**
     * Get attribute usage summary.
     */
    protected function _getAttributeSummary(string $attribute, string $type = 'string', int $limit = 25): array
    {
        $mongodb = $this->db->storage;
        $ops = [
            [
                '$match' => [
                    '$and' => [
                        ['deleted' => false],
                        [$attribute => ['$exists' => true]],
                        ['$or' => [
                            ['owner' => $this->getId()],
                            ['shared' => ['$in' => $this->getShares()]],
                        ]],
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
