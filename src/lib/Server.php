<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
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
use MongoDB\BSON\Binary;
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
     * Password policy.
     *
     * @var string
     */
    protected $password_policy = '/.*/';

    /**
     * Password hash.
     *
     * @var int
     */
    protected $password_hash = PASSWORD_DEFAULT;

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
                case 'password_policy':
                    $this->{$name} = (string) $value;

                break;
                case 'max_file_version':
                case 'max_file_size':
                case 'password_hash':
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
     * Verify group attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    public function validateGroupAttributes(array $attributes): array
    {
        foreach ($attributes as $attribute => &$value) {
            switch ($attribute) {
                case 'namespace':
                case 'description':
                    if (!is_string($value)) {
                        throw new GroupException($attribute.' must be a valid string');
                    }

                break;
                case 'name':
                    if (!is_string($value)) {
                        throw new GroupException($attribute.' must be a valid string');
                    }

                    if ($this->groupExists($value)) {
                        throw new GroupException('group does already exists', GroupException::ALREADY_EXISTS);
                    }

                break;
                case 'member':
                    if (!is_array($value)) {
                        throw new GroupException('member must be an array of user');
                    }

                    $valid = [];
                    foreach ($value as $id) {
                        if ($id instanceof User) {
                            $id = $id->getId();
                        } else {
                            $id = new ObjectId($id);
                            if (!$this->userExists($id)) {
                                throw new UserException('user does not exists', UserException::DOES_NOT_EXISTS);
                            }
                        }

                        if (in_array($id, $valid)) {
                            throw new GroupException('group can only hold a user once', GroupException::UNIQUE_MEMBER);
                        }

                        $valid[] = $id;
                    }

                    $value = $value;

                break;
                default:
                    throw new GroupException('invalid attribute '.$attribute.' given');
            }
        }

        return $attributes;
    }

    /**
     * Verify user attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    public function validateUserAttributes(array $attributes): array
    {
        foreach ($attributes as $attribute => &$value) {
            switch ($attribute) {
                case 'username':
                    if (!preg_match('/^[A-Za-z0-9\.-_\@]$/', $value)) {
                        throw new UserException('username does not match required regex /^[A-Za-z0-9\.-_\@]$/');
                    }

                    if ($this->userExists($value)) {
                        throw new UserException('user does already exists', UserException::ALREADY_EXISTS);
                    }

                break;
                case 'password':
                    if (!preg_match($this->password_policy, $value)) {
                        throw new UserException('password does not follow password policy '.$this->password_policy);
                    }

                    $value = password_hash($password, $this->password_hash);

                break;
                case 'soft_quota':
                case 'hard_quota':
                    if (!is_numeric($value)) {
                        throw new UserException($attribute.' must be numeric');
                    }

                break;
                case 'avatar':
                    if (!$value instanceof Binary) {
                        throw new UserException('avatar must be an instance of Binary');
                    }

                break;
                case 'mail':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new UserException('mail address given is invalid');
                    }

                break;
                case 'admin':
                    $value = (bool) $value;

                break;
                case 'namespace':
                    if (!is_string($value)) {
                        throw new UserException('namespace must be a valid string');
                    }

                break;
                default:
                    throw new UserException('invalid attribute '.$attribute.' given');
            }
        }

        return $attributes;
    }

    /**
     * Add user.
     *
     * @param string $username
     * @param array  $attributes
     *
     * @return ObjectId
     */
    public function addUser(string $username, array $attributes = []): ObjectId
    {
        $attributes['username'] = $username;
        $attributes = $this->validateUserAttributes($attributes);

        $defaults = [
            'created' => new UTCDateTime(),
            'deleted' => false,
        ];

        $attributes = array_merge($defaults, $attributes);
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
    public function addGroup(string $name, array $member = [], array $attributes = []): ObjectId
    {
        $attributes['name'] = $name;
        $attributes['member'] = $member;
        $attributes = $this->validateGroupAttributes($attributes);

        $defaults = [
            'created' => new UTCDateTime(),
            'deleted' => false,
            'member' => [],
        ];

        $attributes = array_merge($attributes, $defaults);
        $result = $this->db->group->insertOne($attributes);

        return $result->getInsertedId();
    }
}
