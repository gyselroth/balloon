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

use Balloon\Filesystem\Storage;
use Balloon\Server\Group;
use Balloon\Server\User;
use Micro\Auth\Identity;
use MongoDB\BSON\ObjectId;
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
     * @param iterable        $config
     */
    public function __construct(Database $db, Storage $storage, LoggerInterface $logger, Hook $hook, ?Iterable $config = null)
    {
        $this->db = $db;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->hook = $hook;

        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Server
     */
    public function setOptions(?Iterable $config = null): Server
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
            return new Filesystem($this, $this->db, $this->hook, $this->logger, $this->storage, $user);
        }
        if ($this->identity instanceof User) {
            return new Filesystem($this, $this->db, $this->hook, $this->logger, $this->storage, $this->identity);
        }

        return new Filesystem($this, $this->db, $this->hook, $this->logger, $this->storage);
    }

    /**
     * Add user.
     *
     * @return bool
     */
    public function addUser(array $user): bool
    {
        if ($this->userExists($user['username'])) {
            throw new Exception('user does already exists');
        }

        $this->db->user->insertOne($user);

        return true;
    }

    /**
     * Check if user exists.
     *
     * @return bool
     */
    public function userExists(string $username): bool
    {
        return null !== $this->db->user->findOne(['username' => $username]);
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
            throw new Exception('user does not exists');
        }

        return new User($attributes, $this, $this->db, $this->logger);
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
            throw new Exception('user does not exists');
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
            throw new Exception('user does not exists');
        }

        return new User($attributes, $this, $this->db, $this->logger);
    }

    /**
     * Get group by name.
     *
     * @param string $name
     *
     * @return User
     */
    public function getGroupByName(string $name): Group
    {
        $attributes = $this->db->group->findOne([
           'username' => $name,
        ]);

        if (null === $attributes) {
            throw new Exception('group does not exists');
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
            throw new Exception('group does not exists');
        }

        return new Group($attributes, $this, $this->db, $this->logger);
    }

    /**
     * Get Filesystem.
     *
     * @return Filesystem
     */
    public function addGroup(Group $group): Filesystem
    {
    }
}
