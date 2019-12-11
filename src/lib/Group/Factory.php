<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Group;

use Balloon\Resource\Factory as ResourceFactory;
use Balloon\Group;
use Balloon\Group\GroupInterface;
use Balloon\File\Factory as FileFactory;
use Generator;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;
use Balloon\User\Factory as UserFactory;

class Factory
{
    /**
     * Collection name.
     */
    public const COLLECTION_NAME = 'groups';

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
     * Initialize.
     */
    public function __construct(Database $db, ResourceFactory $resource_factory, UserFactory $user_factory)
    {
        $this->db = $db;
        $this->resource_factory = $resource_factory;
        $this->user_factory = $user_factory;
    }

    /**
     * Has group.
     */
    public function has(string $name): bool
    {
        return $this->db->{self::COLLECTION_NAME}->count(['name' => $name]) > 0;
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
     * Get group.
     */
    public function getOne(ObjectIdInterface $id): GroupInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            '_id' => $id,
        ]);

        if ($result === null) {
            throw new Exception\NotFound('group '.$name.' is not registered');
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
    public function update(GroupInterface $resource, array $data): bool
    {
        $data['name'] = $resource->getName();
        $data['kind'] = $resource->getKind();

        $resource['members'] = $this->validateMembers($resource['members'] ?? []);

        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }

    /**
     * Validate group members
     *
     * @return array
     */
    protected function validateMembers(array $member): array
    {
        $valid = [];
        foreach ($member as $id) {
            if (!($id instanceof ObjectIdInterface)) {
                $id = new ObjectId($id);
                if (!$this->user_factory->getOne($id)) {
                    throw new User\Exception\NotFound('user '.$id.' does not exists');
                }
            }

            if (!in_array($id, $valid)) {
                $valid[] = $id;
            }
        }

        return $valid;
    }

    /**
     * Add group.
     */
    public function add(array $resource): GroupInterface
    {
        $resource['kind'] = 'Group';

        if ($this->has($resource['name'])) {
            throw new Exception\NotUnique('group '.$resource['name'].' does already exists');
        }

        $resource['members'] = $this->validateMembers($resource['members'] ?? []);

        $resource =  $this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $resource);
        return $this->build($resource);
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
    public function build(array $resource): GroupInterface
    {
        return $this->resource_factory->initResource(new Group($resource));
    }
}
