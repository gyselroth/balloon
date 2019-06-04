<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\AccessRule;

use Balloon\AccessRule;
use Balloon\Resource\Factory as ResourceFactory;
use Generator;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;

class Factory
{
    /**
     * Collection name.
     */
    public const COLLECTION_NAME = 'access_rules';

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
    public function __construct(Database $db, ResourceFactory $resource_factory)
    {
        $this->db = $db;
        $this->resource_factory = $resource_factory;
    }

    /**
     * Has resource.
     */
    public function has(string $name): bool
    {
        return $this->db->{self::COLLECTION_NAME}->count(['name' => $name]) > 0;
    }

    /**
     * Get resources.
     */
    public function getAll(?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $that = $this;

        return $this->resource_factory->getAllFrom($this->db->{self::COLLECTION_NAME}, $query, $offset, $limit, $sort, function ($resource) use ($that) {
            return $that->build($resource);
        });
    }

    /**
     * Get resource.
     */
    public function getOne(string $name): AccessRuleInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            'name' => $name,
        ], [
            'projection' => ['history' => 0],
        ]);

        if ($result === null) {
            throw new Exception\NotFound('access rule '.$name.' is not registered');
        }

        return $this->build($result);
    }

    /**
     * Delete by name.
     */
    public function deleteOne(string $name): bool
    {
        $resource = $this->getOne($name);
        $this->resource_factory->deleteFrom($this->db->{self::COLLECTION_NAME}, $resource->getId());

        return true;
    }

    /**
     * Add resource.
     */
    public function add(array $resource): ObjectIdInterface
    {
        $resource['kind'] = 'AccessRule';
        $resource = $this->resource_factory->validate($resource);

        if ($this->has($resource['name'])) {
            throw new Exception\NotUnique('access rule '.$resource['name'].' does already exists');
        }

        return $this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $resource);
    }

    /**
     * Update.
     */
    public function update(AccessRuleInterface $resource, array $data): bool
    {
        $data['name'] = $resource->getName();
        $data['kind'] = $resource->getKind();
        $data = $this->resource_factory->validate($data);

        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }

    /**
     * Change stream.
     */
    public function watch(?ObjectIdInterface $after = null, bool $existing = true, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $that = $this;

        return $this->resource_factory->watchFrom($this->db->{self::COLLECTION_NAME}, $after, $existing, $query, function ($resource) use ($that) {
            return $that->build($resource);
        }, $offset, $limit, $sort);
    }

    /**
     * Build instance.
     */
    public function build(array $resource): AccessRuleInterface
    {
        return $this->resource_factory->initResource(new AccessRule($resource));
    }
}
