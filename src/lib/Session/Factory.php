<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Session;

use Balloon\Resource\Factory as ResourceFactory;
use Balloon\Session;
use Balloon\Session\SessionInterface;
use Balloon\Node\NodeInterface;
use Balloon\Collection\CollectionInterface;
use Balloon\File\Factory as FileFactory;
use Generator;
use InvalidArgumentException;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;
use Balloon\User\UserInterface;
use resource;

class Factory
{
    /**
     * Collection name.
     */
    public const COLLECTION_NAME = 'sessions';

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
     * Has session.
     */
    public function has(string $name): bool
    {
        return $this->db->{self::COLLECTION_NAME}->count(['sessionname' => $name]) > 0;
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
     * Get session.
     */
    public function getOne(UserInterface $user, ObjectIdInterface $id): SessionInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            'owner' => $user->getId(),
            '_id' => $id,
        ]);

        if ($result === null) {
            throw new Exception\NotFound('session '.$name.' is not registered');
        }

        return $this->build($result);
    }

    /**
     * Delete by name.
     */
    public function deleteOne(UserInterface $user, ObjectIdInterface $id): bool
    {
        $resource = $this->getOne($user, $id);

        return $this->resource_factory->deleteFrom($this->db->{self::COLLECTION_NAME}, $resource->getId());
    }

    /**
     * Update.
     */
    public function update(UserInterface $user, SessionInterface $resource, CollectionInterface $parent, $stream): bool
    {
        $storage = $parent->getStorage();
        $session = $storage->storeTemporaryFile($stream, $user, $resource->getId());
        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }

    /**
     * Add session.
     */
    public function add(UserInterface $user, CollectionInterface $parent, $stream): SessionInterface
    {
          $storage = $parent->getStorage();
          $session = $storage->storeTemporaryFile($stream, $user);

          $resource = [
              '_id' => $session,
              'kind' => 'Session',
              'parent' => $parent->getId(),
              'size' => 0,
              'owner' => $user->getId(),
          ];

        return $this->build($this->resource_factory->addTo($this->db->{self::COLLECTION_NAME}, $resource));
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
    public function build(array $resource): SessionInterface
    {
        return $this->resource_factory->initResource(new Session($resource));
    }
}
