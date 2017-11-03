<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\Collection;
use Balloon\Helper;
use Balloon\Server;
use Micro\Auth\Identity;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use MongoDB\Database;
use Generator;

class Group
{
    /**
     * User unique id.
     *
     * @var ObjectID
     */
    protected $_id;

    /**
     * Name.
     *
     * @var string
     */
    protected $name;

    /**
     * Member.
     *
     * @var array
     */
    protected $member = [];

    /**
     * Last sync timestamp.
     *
     * @var UTCDateTime
     */
    protected $last_attr_sync;

    /**
     * Is group deleted?
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
     * Instance user.
     *
     * @param array  $attributes
     * @param Server $server
     * @param bool   $ignore_deleted
     */
    public function __construct(array $attributes, Server $server, Database $db, LoggerInterface $logger, bool $ignore_deleted = true)
    {
        $this->server = $server;
        $this->db = $db;
        $this->logger = $logger;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        if (true === $this->deleted && false === $ignore_deleted) {
            throw new Exception\NotAuthenticated(
                'user '.$username.' is deleted',
                Exception\NotAuthenticated::USER_DELETED
            );
        }
    }

    /**
     * Return name as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Update user with identity attributes.
     *
     * @param Identity $identity
     *
     * @return bool
     */
    /*public function updateIdentity(Identity $identity): bool
    {
        $attr_sync = $identity->getAdapter()->getAttributeSyncCache();
        if ($attr_sync === -1) {
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
        }
        $this->logger->debug('user auth attribute sync cache is in time', [
                'category' => get_class($this),
            ]);

        return true;
    }*/

    /**
     * Get user attribute.
     *
     * @param array|string $attribute
     *
     * @return mixed
     */
    public function getAttribute($attribute = null)
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
            $requested = (array) $attribute;
        } elseif (is_array($attribute)) {
            $requested = $attribute;
        }

        $resolved = [];
        foreach ($requested as $attr) {
            if (!in_array($attr, $valid, true)) {
                throw new Exception\InvalidArgument('requested attribute '.$attr.' does not exists');
            }

            switch ($attr) {
                case 'id':
                    $resolved['id'] = (string) $this->_id;

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
        }

        return $resolved;
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
     * Get unique id.
     *
     * @return ObjectID
     */
    public function getId(): ObjectID
    {
        return $this->_id;
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
     * Delete user.
     *
     * @param bool $force
     *
     * @return bool
     */
    public function delete(bool $force = false): bool
    {
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get member.
     *
     * @return array
     */
    public function getMember(): array
    {
        return $this->member;
    }


    public function getResolvedMember(): ?Generator
    {
        foreach($this->member as $member) {
            yield $this->server->getUserById($member);
        }

        return null;
    }
}
