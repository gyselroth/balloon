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
use Balloon\Server\Group;
use Balloon\Server\User;
use Generator;
use InvalidArgumentException;
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
    protected $max_file_size = 17179869184;

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
     * Server url.
     *
     * @var string
     */
    protected $server_url = 'https://localhost';

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
                case 'server_url':
                    $this->{$name} = (string) $value;

                break;
                case 'max_file_version':
                case 'max_file_size':
                case 'password_hash':
                    $this->{$name} = (int) $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$name.' given');
            }
        }

        return $this;
    }

    /**
     * Get server url.
     *
     * @return string
     */
    public function getServerUrl(): string
    {
        return $this->server_url;
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
                    if (!is_string($value)) {
                        throw new Group\Exception\InvalidArgument(
                            $attribute.' must be a valid string',
                            Group\Exception\InvalidArgument::INVALID_NAMESPACE
                        );
                    }

                break;
                case 'name':
                    if (!is_string($value)) {
                        throw new Group\Exception\InvalidArgument(
                            $attribute.' must be a valid string',
                            Group\Exception\InvalidArgument::INVALID_NAME
                        );
                    }

                break;
                case 'optional':
                    if (!is_array($value)) {
                        throw new Group\Exception\InvalidArgument(
                            'optional group attributes must be an array',
                            Group\Exception\InvalidArgument::INVALID_OPTIONAL
                        );
                    }

                break;
                case 'member':
                    if (!is_array($value)) {
                        throw new Group\Exception\InvalidArgument(
                            'member must be an array of user',
                            Group\Exception\InvalidArgument::INVALID_MEMBER
                        );
                    }

                    $valid = [];
                    foreach ($value as $id) {
                        if ($id instanceof User) {
                            $id = $id->getId();
                        } else {
                            $id = new ObjectId($id);
                            if (!$this->userExists($id)) {
                                throw new User\Exception\NotFound('user does not exists');
                            }
                        }

                        if (!in_array($id, $valid)) {
                            $valid[] = $id;
                        }
                    }

                    $value = $valid;

                break;
                default:
                    throw new Group\Exception\InvalidArgument(
                        'invalid attribute '.$attribute.' given',
                        Group\Exception\InvalidArgument::INVALID_ATTRIBUTE
                    );
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
                    if (!preg_match('/^[A-Za-z0-9\.-_\@]+$/', $value)) {
                        throw new User\Exception\InvalidArgument(
                            'username does not match required regex /^[A-Za-z0-9\.-_\@]+$/',
                            User\Exception\InvalidArgument::INVALID_USERNAME
                        );
                    }

                    if ($this->userExists($value)) {
                        throw new User\Exception\NotUnique('user does already exists');
                    }

                break;
                case 'password':
                    if (!preg_match($this->password_policy, $value)) {
                        throw new User\Exception\InvalidArgument(
                            'password does not follow password policy '.$this->password_policy,
                            User\Exception\InvalidArgument::INVALID_PASSWORD
                        );
                    }

                    $value = password_hash($value, $this->password_hash);

                break;
                case 'soft_quota':
                case 'hard_quota':
                    if (!is_numeric($value)) {
                        throw new User\Exception\InvalidArgument(
                            $attribute.' must be numeric',
                            User\Exception\InvalidArgument::INVALID_QUOTA
                        );
                    }

                break;
                case 'avatar':
                    if (!$value instanceof Binary) {
                        throw new User\Exception\InvalidArgument(
                            'avatar must be an instance of Binary',
                            User\Exception\InvalidArgument::INVALID_AVATAR
                        );
                    }

                break;
                case 'mail':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new User\Exception\InvalidArgument(
                            'mail address given is invalid',
                            User\Exception\InvalidArgument::INVALID_MAIL
                        );
                    }

                break;
                case 'admin':
                    $value = (bool) $value;

                break;
                case 'locale':
                    if (!preg_match('#^[a-z]{2}_[A-Z]{2}$#', $value)) {
                        throw new User\Exception\InvalidArgument(
                            'invalid locale given, must be according to format a-z_A-Z',
                            User\Exception\InvalidArgument::INVALID_LOCALE
                        );
                    }

                break;
                case 'namespace':
                    if (!is_string($value)) {
                        throw new User\Exception\InvalidArgument(
                            'namespace must be a valid string',
                            User\Exception\InvalidArgument::INVALID_NAMESPACE
                        );
                    }

                break;
                case 'optional':
                    if (!is_array($value)) {
                        throw new User\Exception\InvalidArgument(
                            'optional user attributes must be an array',
                            User\Exception\InvalidArgument::INVALID_OPTIONAL
                        );
                    }

                break;
                default:
                    throw new User\Exception\InvalidArgument(
                        'invalid attribute '.$attribute.' given',
                        User\Exception\InvalidArgument::INVALID_ATTRIBUTE
                    );
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
            'changed' => new UTCDateTime(),
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
    public function usernameExists(string $username): bool
    {
        return  1 === $this->db->user->count(['username' => $username]);
    }

    /**
     * Check if user exists.
     *
     * @return bool
     */
    public function userExists(ObjectId $id): bool
    {
        return  1 === $this->db->user->count(['_id' => $id]);
    }

    /**
     * Check if user exists.
     *
     * @return bool
     */
    public function groupExists(ObjectId $id): bool
    {
        return  1 === $this->db->group->count(['_id' => $id]);
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
        $aggregation = $this->getUserAggregationPipes();
        array_unshift($aggregation, ['$match' => ['_id' => $id]]);
        $users = $this->db->user->aggregate($aggregation)->toArray();

        if (count($users) > 1) {
            throw new User\Exception\NotUnique('multiple user found');
        }

        if (count($users) === 0) {
            throw new User\Exception\NotFound('user does not exists');
        }

        return new User(array_shift($users), $this, $this->db, $this->logger);
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
            '$match' => [
                '_id' => ['$in' => $find],
            ],
        ];

        $aggregation = $this->getUserAggregationPipes();
        array_unshift($aggregation, $filter);
        $users = $this->db->user->aggregate($aggregation);

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
        $user = null;

        try {
            $user = $this->getUserByName($identity->getIdentifier());
        } catch (User\Exception\NotFound $e) {
            $this->logger->warning('failed connect authenticated user, user account does not exists', [
                'category' => get_class($this),
            ]);
        }

        $this->hook->run('preServerIdentity', [$identity, &$user]);

        if (!($user instanceof User)) {
            throw new User\Exception\NotAuthenticated('user does not exists', User\Exception\NotAuthenticated::USER_NOT_FOUND);
        }

        if ($user->isDeleted()) {
            throw new User\Exception\NotAuthenticated(
                'user is disabled and can not be used',
                User\Exception\NotAuthenticated::USER_DELETED
            );
        }

        $this->identity = $user;
        $user->updateIdentity($identity)
             ->updateShares();
        $this->hook->run('postServerIdentity', [$user]);

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
        $aggregation = $this->getUserAggregationPipes();
        array_unshift($aggregation, ['$match' => ['username' => $name]]);
        $users = $this->db->user->aggregate($aggregation)->toArray();

        if (count($users) > 1) {
            throw new User\Exception\NotUnique('multiple user found');
        }

        if (count($users) === 0) {
            throw new User\Exception\NotFound('user does not exists');
        }

        return new User(array_shift($users), $this, $this->db, $this->logger);
    }

    /**
     * Count users.
     *
     * @param array $filter
     *
     * @return int
     */
    public function countUsers(array $filter): int
    {
        return $this->db->user->count($filter);
    }

    /**
     * Count groups.
     *
     * @param array $filter
     *
     * @return int
     */
    public function countGroups(array $filter): int
    {
        return $this->db->group->count($filter);
    }

    /**
     * Get users.
     *
     * @param array $filter
     * @param int   $offset
     * @param int   $limit
     *
     * @return Generator
     */
    public function getUsers(array $filter, ?int $offset = null, ?int $limit = null): Generator
    {
        $aggregation = $this->getUserAggregationPipes();

        if (count($filter) > 0) {
            array_unshift($aggregation, ['$match' => $filter]);
        }

        if ($offset !== null) {
            array_unshift($aggregation, ['$skip' => $offset]);
        }

        if ($limit !== null) {
            $aggregation[] = ['$limit' => $limit];
        }

        $users = $this->db->user->aggregate($aggregation);

        foreach ($users as $attributes) {
            yield new User($attributes, $this, $this->db, $this->logger);
        }

        return $this->db->user->count($filter);
    }

    /**
     * Get groups.
     *
     * @param array $filter
     * @param int   $offset
     * @param int   $limit
     *
     * @return Generator
     */
    public function getGroups(array $filter, ?int $offset = null, ?int $limit = null): Generator
    {
        $groups = $this->db->group->find($filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);

        foreach ($groups as $attributes) {
            yield new Group($attributes, $this, $this->db, $this->logger);
        }

        return $this->db->group->count($filter);
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
        $group = $this->db->group->findOne([
           'name' => $name,
        ]);

        if (null === $group) {
            throw new Group\Exception\NotFound('group does not exists');
        }

        return new Group($group, $this, $this->db, $this->logger);
    }

    /**
     * Get group by id.
     *
     * @param string $id
     *
     * @return Group
     */
    public function getGroupById(ObjectId $id): Group
    {
        $group = $this->db->group->findOne([
           '_id' => $id,
        ]);

        if (null === $group) {
            throw new Group\Exception\NotFound('group does not exists');
        }

        return new Group($group, $this, $this->db, $this->logger);
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
        $attributes['member'] = $member;
        $attributes['name'] = $name;
        $attributes = $this->validateGroupAttributes($attributes);

        $defaults = [
            'created' => new UTCDateTime(),
            'changed' => new UTCDateTime(),
            'deleted' => false,
        ];

        $attributes = array_merge($attributes, $defaults);
        $result = $this->db->group->insertOne($attributes);

        return $result->getInsertedId();
    }

    /**
     * Get user aggregation pipe.
     *
     * @return array
     */
    protected function getUserAggregationPipes(): array
    {
        return [
            ['$lookup' => [
                'from' => 'group',
                'localField' => '_id',
                'foreignField' => 'member',
                'as' => 'groups',
            ]],
            ['$addFields' => [
                'groups' => [
                    '$map' => [
                        'input' => '$groups',
                        'as' => 'group',
                        'in' => '$$group._id',
                    ],
                ],
            ]],
        ];
    }
}
