<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Event;

use Balloon\Resource\Factory as ResourceFactory;
use Balloon\Event;
use Balloon\Event\EventInterface;
use Balloon\Node\NodeInterface;
use Balloon\File\Factory as FileFactory;
use Generator;
use InvalidArgumentException;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;
use Balloon\User\UserInterface;

class Factory
{
    /**
     * Collection name.
     */
    public const COLLECTION_NAME = 'events';

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
     * Has event.
     */
    public function has(string $name): bool
    {
        return $this->db->{self::COLLECTION_NAME}->count(['eventname' => $name]) > 0;
    }

    /**
     * Prepare query.
     */
    public function prepareQuery(UserInterface $user, ?array $query = null): array
    {
        $filter = [
            'owner' => $user->getId(),
        ];

        if (!empty($query)) {
            $filter = [
                '$and' => [$filter, $query],
            ];
        }

        return $filter;
    }

    /**
     * Get all.
     */
    public function getAll(UserInterface $user, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $query = $this->prepareQuery($user, $query);
        $that = $this;

        return $this->resource_factory->getAllFrom($this->db->{self::COLLECTION_NAME}, $query, $offset, $limit, $sort, function (array $resource) use ($that) {
            return $that->build($resource);
        });
    }

    /**
     * Update.
     */
    public function update(EventInterface $resource, array $data): bool
    {
        $data['name'] = $resource->getName();
        $data['kind'] = $resource->getKind();

        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }

    /**
     * Add event.
     */
    public function add(UserInterface $user, NodeInterface $node, array $resource): EventInterface
    {
        $resource['kind'] = 'Event';
        $resource['owner'] = $user->getId();
        $resource['node'] = [
            'id' => $node->getId(),
            'path' => $node->getPath(),
            'name' => $node->getName(),
            'parent' => $node->getParent()->getRealId(),
            'share' => $node->isShareMember() ? $node->getShareId() : null,
        ];

        $result = $this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $resource);
        return $this->build($result);
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
    public function build(array $resource): EventInterface
    {
        return $this->resource_factory->initResource(new Event($resource));
    }
}
