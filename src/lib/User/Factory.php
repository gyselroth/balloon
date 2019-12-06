<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\User;

use Balloon\Resource\Factory as ResourceFactory;
use Balloon\User;
use Balloon\User\UserInterface;
use Balloon\File\Factory as FileFactory;
use Generator;
use InvalidArgumentException;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;

class Factory
{
    /**
     * Collection name.
     */
    public const COLLECTION_NAME = 'users';

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Resource factory.
     *
     * @var ResourceFactory
     */
    protected $resource_factory;

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
     */
    public function __construct(Database $db, ResourceFactory $resource_factory, array $options = [])
    {
        $this->db = $db;
        $this->resource_factory = $resource_factory;
        $this->setOptions($options);
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = []): self
    {
        foreach ($config as $name => $value) {
            switch ($name) {
                case 'password_policy':
                    $this->{$name} = (string) $value;

                break;
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
     * Has user.
     */
    public function has(string $name): bool
    {
        return $this->db->{self::COLLECTION_NAME}->count(['username' => $name]) > 0;
    }

    /**
     * Get all.
     */
    public function getAll(?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $that = $this;

        return $this->resource_factory->getAllFrom($this->db->{self::COLLECTION_NAME}, $query, $offset, $limit, $sort, function (array $resource) use ($that) {
            return $that->build($resource);
        });
    }

    /**
     * Get one by name.
     */
    public function getOneByName(string $name): UserInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            '$or' => [
                ['username' => $name],
                ['mail' => $name],
            ],
        ]);

        if ($result === null) {
            throw new Exception\NotFound('user '.$name.' is not registered');
        }

        return $this->build($result);
    }


    /**
     * Get user.
     */
    public function getOne(ObjectIdInterface $id): UserInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            '_id' => $id,
        ]);

        if ($result === null) {
            throw new Exception\NotFound('user '.$name.' is not registered');
        }

        return $this->build($result);
    }

    /**
     * Delete by name.
     */
    public function deleteOne(ObjectIdInterface $id): bool
    {
        $resource = $this->getOne($id);

        return $this->resource_factory->deleteFrom($this->db->{self::COLLECTION_NAME}, $resource->getId());
    }

    /**
     * Update.
     */
    public function update(UserInterface $resource, array $data): bool
    {
        $data['name'] = $resource->getName();
        $data['kind'] = $resource->getKind();

        if (isset($data['password'])) {
            $data = Validator::validatePolicy($data, $this->password_policy);
            $data['hash'] = password_hash($data['password'], $this->password_hash);
            unset($data['password']);
        }

        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }

    /**
     * Add user.
     */
    public function add(array $resource): ObjectIdInterface
    {
        $resource['kind'] = 'User';
        Validator::validatePolicy($resource, $this->password_policy);

        if ($this->has($resource['username'])) {
            throw new Exception\NotUnique('user '.$resource['username'].' does already exists');
        }

        if (isset($resource['password'])) {
            $resource['hash'] = password_hash($resource['password'], $this->password_hash);
            unset($resource['password']);
        }

        return $this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $resource);
    }

    /**
     * Change stream.
     */
    public function watch(?ObjectIdInterface $after = null, bool $existing = true, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $that = $this;

        return $this->resource_factory->watchFrom($this->db->{self::COLLECTION_NAME}, $after, $existing, $query, function (array $resource) use ($that) {
            return $that->build($resource);
        }, $offset, $limit, $sort);
    }

    /**
     * Build instance.
     */
    public function build(array $resource): UserInterface
    {
        return $this->resource_factory->initResource(new User($resource));
    }

    /**
     * Get used qota.
     */
    public function getQuotaUsage(UserInterface $user): int
    {
        $result = $this->db->{FileFactory::COLLECTION_NAME}->aggregate([
            [
                '$match' => [
                    'owner' => $user->getId(),
                    'kind' => 'File',
                    'deleted' => null,
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

        return $sum;
    }
}
