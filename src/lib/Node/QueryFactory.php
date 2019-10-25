<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Node;

use Balloon\Exception;
use Balloon\Resource\Factory as ResourceFactory;
use Balloon\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Balloon\Storage\Factory as StorageFactory;
use Balloon\User\UserInterface;
use Generator;
use League\Event\Emitter;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Balloon\Collection\Factory as CollectionFactory;
use MongoDB\BSON\ObjectIdInterface;

class QueryFactory
{
    public const COLLECTION_NAME = 'nodes';

    /**
     * Database.
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
     * Emitter.
     *
     * @var Emitter
     */
    protected $emitter;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Storage.
     *
     * @var StorageAdapterInterface
     */
    protected $storage;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Storage cache.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Initialize.
     */
    public function __construct(Database $db, Emitter $emitter, ResourceFactory $resource_factory, LoggerInterface $logger, StorageAdapterInterface $storage, Acl $acl, CollectionFactory $collection_factory)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->emitter = $emitter;
        $this->storage = $storage;
        $this->acl = $acl;
        $this->resource_factory = $resource_factory;
        $this->collection_factory = $collection_factory;
    }

    /**
     * Has namespace.
     */
    public function has(UserInterface $user, string $name): bool
    {
        return $this->db->{self::COLLECTION_NAME}->count([
            'name' => $name,
            'namespace' => $namespace->getName(),
        ]) > 0;
    }

    /**
     * Get all.
     */
    public function getAll(UserInterface $user, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $filter = $this->prepareQuery($user, $query);
        $that = $this;

        return $this->resource_factory->getAllFrom($this->db->{self::COLLECTION_NAME}, $filter, $offset, $limit, $sort, function (array $resource) use ($user, $that) {
            return $that->build($result, $user);
        });
    }

    public function build(array $resource, UserInterface $user) {
        switch($resource['kind']) {
            case 'Collection':
                return $this->collection_factory->build($resource, $user);
            case 'File':
                return $this->file_factory->build($resource, $user);
            default:
                //TODO throw

        }

    }

    /**
     * Get one.
     */
    public function getOne(UserInterface $user, ObjectIdInterface $node): NodeInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            '_id' => $node,
        ]);

        if ($result === null) {
            throw new Exception\NotFound('node '.$node.' is not registered');
        }

        return $this->build($result, $user);
    }

    /**
     * Delete by name.
     */
    public function deleteOne(UserInterface $user, ObjectIdInterface $node): bool
    {
        $resource = $this->getOne($user, $node);

        switch($resource->getKind()) {
            case 'Collection':
                return $this->collection_factory->deleteOne($user, $resource);
            case 'File':
                return $this->collection_factory->deleteOne($user, $resource);
            default:
                //TODO throw
        }
    }

    /**
     * Add namespace.
     */
    public function add(UserInterface $user, array $resource): ObjectIdInterface
    {
        if ($this->has($namespace, $resource['name'])) {
            throw new Exception\NotUnique('collection '.$resource['name'].' does already exists');
        }

        $resource['namespace'] = $namespace->getName();

    }

    /**
     * Update.
     */
    public function update(NodeInterface $resource, array $data): bool
    {
        $data['name'] = $resource->getName();
        $data['kind'] = 'Collection';
        $data = $this->resource_factory->validate($data);

        return $this->resource_factory->updateIn($this->db->{self::COLLECTION_NAME}, $resource, $data);
    }

    /**
     * Change stream.
     */
    public function watch(UserInterface $user, ?ObjectIdInterface $after = null, bool $existing = true, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $filter = $this->prepareQuery($namespace, $query);
        $that = $this;

        return $this->resource_factory->watchFrom($this->db->{self::COLLECTION_NAME}, $after, $existing, $filter, function (array $resource) use ($namespace, $that) {
            return $that->build($resource, $namespace);
        }, $offset, $limit, $sort);
    }










    /**
     * Find raw node.
     */
    public function findRawNode(ObjectId $id): array
    {
        if (isset($this->raw_cache[(string) $id])) {
            return $this->raw_cache[(string) $id];
        }

        $node = $this->db->storage->findOne(['_id' => $id]);
        if (null === $node) {
            throw new Exception\NotFound(
                'node '.$id.' not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $this->raw_cache[(string) $id] = $node;

        return $node;
    }

    /**
     * Factory loader.
     */
    public function findNodeById($id, ?string $class = null, int $deleted = NodeInterface::DELETED_INCLUDE): NodeInterface
    {
        if (isset($this->cache[(string) $id])) {
            return $this->cache[(string) $id];
        }

        if (!is_string($id) && !($id instanceof ObjectId)) {
            throw new Exception\InvalidArgument($id.' node id has to be a string or instance of \MongoDB\BSON\ObjectId');
        }

        try {
            if (is_string($id)) {
                $id = new ObjectId($id);
            }
        } catch (\Exception $e) {
            throw new Exception\InvalidArgument('invalid node id specified');
        }

        $filter = [
            '_id' => $id,
        ];

        switch ($deleted) {
            case NodeInterface::DELETED_INCLUDE:
                break;
            case NodeInterface::DELETED_EXCLUDE:
                $filter['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $filter['deleted'] = ['$type' => 9];

                break;
        }

        $node = $this->db->storage->findOne($filter);

        return $this->build($node);
        /*
        $node = $this->db->storage->findOne($filter);

        if (null === $node) {
            throw new Exception\NotFound(
                'node ['.$id.'] not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $return = $this->initNode($node);

        if (null !== $class && !($return instanceof $class)) {
            throw new Exception('node '.get_class($return).' is not instance of '.$class);
        }

        return $return;*/
    }

    /**
     * Load nodes by id.
     */
    public function findNodesById(array $id = [], ?string $class = null, int $deleted = NodeInterface::DELETED_INCLUDE): Generator
    {
        $find = [];
        foreach ($id as $i) {
            $find[] = new ObjectId($i);
        }

        $filter = [
            '_id' => ['$in' => $find],
        ];

        switch ($deleted) {
            case NodeInterface::DELETED_INCLUDE:
                break;
            case NodeInterface::DELETED_EXCLUDE:
                $filter['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $filter['deleted'] = ['$type' => 9];

                break;
        }

        $result = $this->db->storage->find($filter);

        $nodes = [];
        foreach ($result as $node) {
            try {
                $return = $this->initNode($node);

                if (in_array($return->getId(), $nodes)) {
                    continue;
                }

                $nodes[] = $return->getId();
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                continue;
            }

            if (null !== $class && !($return instanceof $class)) {
                throw new Exception('node is not an instance of '.$class);
            }

            yield $return;
        }
    }

    /**
     * Load nodes by id.
     *
     * @param null|mixed $class
     */
    public function getNodes(?array $id = null, $class = null, int $deleted = NodeInterface::DELETED_EXCLUDE): Generator
    {
        return $this->findNodesById($id, $class, $deleted);
    }

    /**
     * Load node.
     *
     * @param null|mixed $id
     * @param null|mixed $class
     */
    public function getNode($id = null, $class = null, bool $multiple = false, bool $allow_root = false, ?int $deleted = null): NodeInterface
    {
        if (empty($id)) {
            if (true === $allow_root) {
                return $this->getRoot();
            }

            throw new Exception\InvalidArgument('invalid id given');
        }

        if (null === $deleted) {
            $deleted = NodeInterface::DELETED_INCLUDE;
        }

        if (true === $multiple && is_array($id)) {
            return $this->findNodesById($id, $class, $deleted);
        }

        return $this->findNodeById($id, $class, $deleted);
    }

    /**
     * Find node with custom filter.
     */
    public function findNodeByFilter(array $filter): NodeInterface
    {
        $result = $this->db->storage->findOne($filter);
        if (null === $result) {
            throw new Exception\NotFound(
                'node with custom filter was not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        return $this->initNode($result);
    }

    /**
     * Count.
     */
    public function countNodes(array $filter = []): int
    {
        return $this->db->storage->count($filter);
    }

    /**
     * Find nodes with custom filters.
     */
    public function findNodesByFilter(array $filter, ?int $offset = null, ?int $limit = null): Generator
    {
        $result = $this->db->storage->find($filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);

        $count = $this->countNodes($filter);

        foreach ($result as $node) {
            try {
                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return $count;
    }

    /**
     * Find nodes with custom filter recursive.
     */
    public function findNodesByFilterRecursiveToArray(Collection $collection, array $filter = []): array
    {
        $graph = [
            'from' => 'storage',
            'startWith' => '$pointer',
            'connectFromField' => 'pointer',
            'connectToField' => 'parent',
            'as' => 'children',
        ];

        if (count($filter) > 0) {
            $graph['restrictSearchWithMatch'] = $filter;
        }

        $query = [
            ['$match' => ['_id' => $collection->getId()]],
            ['$graphLookup' => $graph],
            ['$unwind' => '$children'],
            ['$project' => ['id' => '$children._id']],
        ];

        $result = $this->db->storage->aggregate($query);

        return array_column(iterator_to_array($result), 'id');
    }

    /**
     * Find nodes with custom filter recursive.
     */
    public function findNodesByFilterRecursive(Collection $collection, array $filter = [], ?int $offset = null, ?int $limit = null): Generator
    {
        $graph = [
            'from' => 'storage',
            'startWith' => '$pointer',
            'connectFromField' => 'pointer',
            'connectToField' => 'parent',
            'as' => 'children',
        ];

        if (count($filter) > 0) {
            $graph['restrictSearchWithMatch'] = $filter;
        }

        $query = [
            ['$match' => ['_id' => $collection->getId()]],
            ['$graphLookup' => $graph],
            ['$unwind' => '$children'],
            ['$group' => ['_id' => null, 'total' => ['$sum' => 1]]],
        ];

        $result = $this->db->storage->aggregate($query);

        $total = 0;
        $result = iterator_to_array($result);
        if (count($result) > 0) {
            $total = $result[0]['total'];
        }

        array_pop($query);
        $query[] = ['$skip' => $offset];
        $query[] = ['$limit' => $limit];
        $result = $this->db->storage->aggregate($query);

        foreach ($result as $node) {
            try {
                if (isset($node['children'])) {
                    $node = $node['children'];
                }

                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return $total;
    }

    /**
     * Get custom filtered children.
     */
    public function findNodesByFilterUser(int $deleted, array $filter, ?int $offset = null, ?int $limit = null): Generator
    {
        $shares = $this->user->getShares();
        $stored_filter = ['$and' => [
            [],
            ['$or' => [
                ['owner' => $this->user->getId()],
                ['shared' => ['$in' => $shares]],
            ]],
        ]];

        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $stored_filter['$and'][0]['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $stored_filter['$and'][0]['deleted'] = ['$type' => 9];
        }

        $stored_filter['$and'][0] = array_merge($filter, $stored_filter['$and'][0]);

        $result = $this->db->storage->find($stored_filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);

        $count = $this->db->storage->count($stored_filter);

        foreach ($result as $node) {
            try {
                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return $count;
    }

    /**
     * Init node.
     */
    public function initNode(array $node): NodeInterface
    {
        $id = $node['_id'];

        if (isset($node['shared']) && true === $node['shared'] && null !== $this->user && $node['owner'] != $this->user->getId()) {
            $node = $this->findReferenceNode($node);
        }

        if (isset($node['parent'])) {
            $parent = $this->findNodeById($node['parent']);
        } elseif ($node['_id'] !== null) {
            $parent = $this->getRoot();
        } else {
            $parent = null;
        }

        if (!array_key_exists('directory', $node)) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        $instance = $this->node_factory->build($this, $node, $parent);

        if (!$this->acl->isAllowed($instance, 'r')) {
            if ($instance->isReference()) {
                $instance->delete(true);
            }

            throw new ForbiddenException(
                'not allowed to access node',
                ForbiddenException::NOT_ALLOWED_TO_ACCESS
            );
        }

        $loaded = isset($this->cache[(string) $node['_id']]);

        if ($loaded === false) {
            $this->cache[(string) $node['_id']] = $instance;
        }

        if ($loaded === false && isset($node['destroy']) && $node['destroy'] instanceof UTCDateTime && $node['destroy']->toDateTime()->format('U') <= time()) {
            $this->logger->info('node ['.$node['_id'].'] is not accessible anmyore, destroy node cause of expired destroy flag', [
                'category' => get_class($this),
            ]);

            $instance->delete(true);

            throw new Exception\Conflict('node is not available anymore');
        }

        if (PHP_SAPI === 'cli') {
            unset($this->cache[(string) $node['_id']]);
        }

        return $instance;
    }

    /**
     * Find node with path.
     */
    public function findNodeByPath(string $path = '', ?string $class = null): NodeInterface
    {
        if (empty($path) || '/' !== $path[0]) {
            $path = '/'.$path;
        }
        $last = strlen($path) - 1;
        if ('/' === $path[$last]) {
            $path = substr($path, 0, -1);
        }
        $parts = explode('/', $path);
        $parent = $this->getRoot();
        array_shift($parts);
        $count = count($parts);
        $i = 0;
        $filter = [];
        foreach ($parts as $node) {
            ++$i;
            if ($count === $i && $class !== null) {
                $filter = [
                    'directory' => ($class === Collection::class),
                ];
            }

            try {
                $parent = $parent->getChild($node, NodeInterface::DELETED_EXCLUDE, $filter);
            } catch (Exception\NotFound $e) {
                if ($count == $i) {
                    $parent = $parent->getChild($node, NodeInterface::DELETED_INCLUDE, $filter);
                } else {
                    throw $e;
                }
            }
        }
        if (null !== $class && !($parent instanceof $class)) {
            throw new Exception('node is not instance of '.$class);
        }

        return $parent;
    }

    protected function getRoot()
    {
        return new Collection([], /*$fs,*/ $this->logger, $this->emitter, $this->acl, null, $this->storage);
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
     * Resolve shared node to reference or share depending who requested.
     */
    protected function findReferenceNode(array $node): array
    {
        if (isset($node['reference']) && ($node['reference'] instanceof ObjectId)) {
            $this->logger->debug('reference node ['.$node['_id'].'] requested from share owner, trying to find the shared node', [
                'category' => get_class($this),
            ]);

            $result = $this->db->storage->findOne([
                'owner' => $this->user->getId(),
                'shared' => true,
                '_id' => $node['reference'],
            ]);

            if (null === $result) {
                throw new Exception\NotFound(
                    'no share node for reference node '.$node['reference'].' found',
                    Exception\NotFound::SHARE_NOT_FOUND
                );
            }
        } else {
            $this->logger->debug('share node ['.$node['_id'].'] requested from member, trying to find the reference node', [
                'category' => get_class($this),
            ]);

            $result = $this->db->storage->findOne([
                'owner' => $this->user->getId(),
                'shared' => true,
                'reference' => $node['_id'],
            ]);

            if (null === $result) {
                throw new Exception\NotFound(
                    'no share reference for node '.$node['_id'].' found',
                    Exception\NotFound::REFERENCE_NOT_FOUND
                );
            }
        }

        return $result;
    }
}
