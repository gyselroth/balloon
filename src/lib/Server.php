<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Storage;
use Balloon\Server\Exception;
use Balloon\Server\Group;
use Balloon\Server\Group\Exception as GroupException;
use Balloon\Server\User;
use Balloon\Server\User\Exception as UserException;
use Generator;
use Micro\Auth\Identity;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Server
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Storage.
     *
     * @var Storage
     */
    protected $storage;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Authenticated identity.
     *
     * @var User
     */
    protected $identity;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Temporary store.
     *
     * @var string
     */
    protected $temp_dir = '/tmp/balloon';

    /**
     * Max file version.
     *
     * @var int
     */
    protected $max_file_version = 8;

    /**
     * Max file size.
     *
     * @var int
     */
    protected $max_file_size = 1073741824;

    /**
     * Initialize.
     *
     * @param Database        $db
     * @param Storage         $storage
     * @param LoggerInterface $logger
     * @param Hook            $hook
     * @param Acl             $acl
     * @param iterable        $config
     */
    public function __construct(Database $db, Storage $storage, LoggerInterface $logger, Hook $hook, Acl $acl, ?Iterable $config = null)
    {
        $this->db = $db;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->hook = $hook;
        $this->acl = $acl;

        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Server
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $name => $value) {
            switch ($name) {
                case 'temp_dir':
                    $this->temp_dir = (string) $value;

                break;
                case 'max_file_version':
                case 'max_file_size':
                    $this->{$name} = (int) $value;

                break;
                default:
                    throw new Exception('invalid option '.$name.' given');
            }
        }

        return $this;
    }

    /**
     * Get temporary directory.
     *
     * @return string
     */
    public function getTempDir(): string
    {
        return $this->temp_dir;
    }

    /**
     * Get max file version.
     *
     * @return int
     */
    public function getMaxFileVersion(): int
    {
        return $this->max_file_version;
    }

    /**
     * Get max file size.
     *
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->max_file_size;
    }

    /**
     * Filesystem factory.
     *
     * @return Filesystem
     */
    public function getFilesystem(?User $user = null): Filesystem
    {
        if (null !== $user) {
            return new Filesystem($this, $this->db, $this->hook, $this->logger, $this->storage, $this->acl, $user);
        }
        if ($this->identity instanceof User) {
            return new Filesystem($this, $this->db, $this->hook, $this->logger, $this->storage, $this->acl, $this->identity);
        }

        return new Filesystem($this, $this->db, $this->hook, $this->logger, $this->storage, $this->acl);
    }

    /**
     * Add user.
     *
     * @param string $username
     * @param string $password
     * @param array  $attributes
     *
     * @return ObjectId
     */
    public function addUser(string $username, ?string $password = null, array $attributes = []): ObjectId
    {
        if ($this->userExists($username)) {
            throw new UserException('user does already exists', UserException::ALREADY_EXISTS);
        }

        $defaults = [
            'deleted' => false,
        ];

        $attributes = array_merge($defaults, $attributes);
        $attributes['created'] = new UTCDateTime();
        $attributes['username'] = $username;

        if (null !== $password) {
            $attributes['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $result = $this->db->user->insertOne($attributes);

        return $result->getInsertedId();
    }

    /**
     * Check if user exists.
     *
     * @return bool
     */
    public function userExists(string $username): bool
    {
        return  1 === $this->db->user->count(['username' => $username]);
    }

    /**
     * Check if group exists.
     *
     * @return bool
     */
    public function groupExists(string $name): bool
    {
        return  1 === $this->db->group->count(['name' => $name]);
    }

    /**
     * Get user by id.
     *
     * @param ObjectId $id
     *
     * @return User
     */
    public function getUserById(ObjectId $id): User
    {
        $attributes = $this->db->user->findOne([
           '_id' => $id,
        ]);

        if (null === $attributes) {
            throw new UserException('user does not exists', UserException::DOES_NOT_EXISTS);
        }

        return new User($attributes, $this, $this->db, $this->logger);
    }

    /**
     * Get users by id.
     *
     * @param array $id
     *
     * @return Generator
     */
    public function getUsersById(array $id): Generator
    {
        $find = [];
        foreach ($id as $i) {
            $find[] = new ObjectId($i);
        }

        $filter = [
            '_id' => ['$in' => $find],
        ];

        $users = $this->db->user->find($filter);

        foreach ($users as $attributes) {
            yield new User($attributes, $this, $this->db, $this->logger);
        }
    }

    /**
     * Set Identity.
     *
     * @param Identity $identity
     *
     * @return bool
     */
    public function setIdentity(Identity $identity): bool
    {
        $result = $this->db->user->findOne(['username' => $identity->getIdentifier()]);
        $this->hook->run('preServerIdentity', [$identity, &$result]);

        if (null === $result) {
            throw new Exception\NotAuthenticated('user does not exists', Exception\NotAuthenticated::USER_DOES_NOT_EXISTS);
        }

        if (isset($result['deleted']) && true === $result['deleted']) {
            throw new Exception\NotAuthenticated(
                'user is disabled and can not be used',
                Exception\NotAuthenticated::USER_DELETED
            );
        }

        $user = new User($result, $this, $this->db, $this->logger);
        $this->identity = $user;
        $user->updateIdentity($identity);
        $this->hook->run('postServerIdentity', [$this, $user]);

        return true;
    }

    /**
     * Get authenticated user.
     *
     * @return User
     */
    public function getIdentity(): ?User
    {
        return $this->identity;
    }

    /**
     * Get user by name.
     *
     * @param string $name
     *
     * @return User
     */
    public function getUserByName(string $name): User
    {
        $attributes = $this->db->user->findOne([
           'username' => $name,
        ]);

        if (null === $attributes) {
            throw new UserException('user does not exists', UserException::DOES_NOT_EXISTS);
        }

        return new User($attributes, $this, $this->db, $this->logger);
    }

    /**
     * Get users.
     *
     * @param array $filter
     *
     * @return Generator
     */
    public function getUsers(array $filter): Generator
    {
        $users = $this->db->user->find($filter);

        foreach ($users as $attributes) {
            yield new User($attributes, $this, $this->db, $this->logger);
        }
    }

    /**
     * Get groups.
     *
     * @param array $filter
     *
     * @return Generator
     */
    public function getGroups(array $filter): Generator
    {
        $groups = $this->db->group->find($filter);

        foreach ($groups as $attributes) {
            yield new Group($attributes, $this, $this->db, $this->logger);
        }
    }

    /**
     * Get group by name.
     *
     * @param string $name
     *
     * @return Group
     */
    public function getGroupByName(string $name): Group
    {
        $attributes = $this->db->group->findOne([
           'username' => $name,
        ]);

        if (null === $attributes) {
            throw new GroupException('group does not exists', GroupException::DOES_NOT_EXISTS);
        }

        return new Group($attributes, $this, $this->db, $this->logger);
    }

    /**
     * Get group by id.
     *
     * @param string $id
     *
     * @return User
     */
    public function getGroupById(ObjectId $id): Group
    {
        $attributes = $this->db->group->findOne([
           '_id' => $id,
        ]);

        if (null === $attributes) {
            throw new GroupException('group does not exists', GroupException::DOES_NOT_EXISTS);
        }

        return new Group($attributes, $this, $this->db, $this->logger);
    }

    /**
     * Add group.
     *
     * @param string $name
     * @param array  $member
     * @param array  $attributes
     *
     * @return ObjectId
     */
    public function addGroup(string $name, array $member, array $attributes = []): ObjectId
    {
        if ($this->groupExists($name)) {
            throw new GroupException('group does already exists', GroupException::ALREADY_EXISTS);
        }

        $defaults = [
            'name' => $name,
            'created' => new UTCDateTime(),
            'deleted' => false,
            'member' => [],
        ];

        $attributes = array_merge($attributes, $defaults);

        foreach ($member as $id) {
            $id = new ObjectId($id);
            if (!$this->userExists($id)) {
                throw new UserException('user does not exists', UserException::DOES_NOT_EXISTS);
            }

            if (!in_array($id, $attributes['member'], true)) {
                throw new GroupException('group can only hold a user once', GroupException::UNIQUE_MEMBER);
            }

            $attributes['member'][] = $id;
        }

        $result = $this->db->group->insertOne($attributes);

        return $result->getInsertedId();
    }
}
