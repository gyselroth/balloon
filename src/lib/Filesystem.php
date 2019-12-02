<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Delta;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\Factory as NodeFactory;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server\User;
use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Filesystem
{
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
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Root collection.
     *
     * @var Collection
     */
    protected $root;

    /**
     * User.
     *
     * @var Delta
     */
    protected $delta;

    /**
     * Get user.
     *
     * @var User
     */
    protected $user;

    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $node_factory;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Cache.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * RAW Cache.
     *
     * @var array
     */
    protected $raw_cache = [];

    /**
     * Initialize.
     */
    public function __construct(Server $server, Database $db, Hook $hook, LoggerInterface $logger, NodeFactory $node_factory, Acl $acl, ?User $user = null)
    {
        $this->user = $user;
        $this->server = $server;
        $this->db = $db;
        $this->logger = $logger;
        $this->hook = $hook;
        $this->node_factory = $node_factory;
        $this->acl = $acl;
    }

    /**
     * Get user.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Get server.
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * Get database.
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }

    /**
     * Get root.
     */
    public function getRoot(): Collection
    {
        if ($this->root instanceof Collection) {
            return $this->root;
        }

        return $this->root = $this->initNode([
            'directory' => true,
            '_id' => null,
            'owner' => $this->user ? $this->user->getId() : null,
        ]);
    }

    /**
     * Get delta.
     */
    public function getDelta(): Delta
    {
        if ($this->delta instanceof Delta) {
            return $this->delta;
        }

        return $this->delta = new Delta($this, $this->db, $this->acl);
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
            throw new Exception\NotFound('node '.$id.' not found', Exception\NotFound::NODE_NOT_FOUND);
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

        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $filter['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $filter['deleted'] = ['$type' => 9];
        }

        $result = iterator_to_array($this->findNodesByFilterRecursiveChildren($filter, $deleted, 0, 1));

        if (count($result) === 0) {
            throw new Exception\NotFound('node ['.$id.'] not found', Exception\NotFound::NODE_NOT_FOUND);
        }

        $node = array_shift($result);
        if (null !== $class && !($node instanceof $class)) {
            throw new Exception('node '.get_class($node).' is not instance of '.$class);
        }

        return $node;
    }

    /**
     * Find one.
     */
    public function findOne(array $filter, int $deleted = NodeInterface::DELETED_INCLUDE, ?Collection $parent = null): NodeInterface
    {
        $result = iterator_to_array($this->findNodesByFilterRecursiveChildren($filter, $deleted, 0, 1, $parent));

        if (count($result) === 0) {
            throw new Exception\NotFound('requested node not found', Exception\NotFound::NODE_NOT_FOUND);
        }

        return array_shift($result);
    }

    /**
     * Load nodes by id.
     *
     * @deprecated
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
     *
     * @deprecated
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
     *
     * @deprecated
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
            throw new Exception\NotFound('node with custom filter was not found', Exception\NotFound::NODE_NOT_FOUND);
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
     * Get deleted nodes.
     *
     * Note this query excludes deleted nodes which have a deleted parent
     */
    public function getTrash(array $query = [], ?int $offset = null, ?int $limit = null): Generator
    {
        $shares = $this->user->getShares();
        $parent_filter = ['$and' => [
            ['deleted' => ['$ne' => false]],
            ['$or' => [
                ['owner' => $this->user->getId()],
                ['shared' => ['$in' => $shares]],
            ]],
        ]];

        if (count($query) > 0) {
            $parent_filter = [
                '$and' => [$parent_filter, $query],
            ];
        }

        $query = [
            ['$match' => $parent_filter],
            ['$graphLookup' => [
                'from' => 'storage',
                'startWith' => '$parent',
                'connectFromField' => 'parent',
                'connectToField' => 'pointer',
                'as' => 'parents',
                'maxDepth' => 0,
                'restrictSearchWithMatch' => [
                    '$or' => [
                        [
                            'shared' => true,
                            'owner' => $this->user->getId(),
                        ],
                        [
                            'shared' => ['$ne' => true],
                        ],
                    ],
                ],
            ]], [
                '$addFields' => [
                    'parents' => [
                        '$arrayElemAt' => ['$parents', 0],
                    ],
                ],
            ], [
                '$match' => [
                    '$or' => [
                        ['parents' => null],
                        ['parents.deleted' => false],
                    ],
                ],
            ], ['$graphLookup' => [
                'from' => 'storage',
                'startWith' => '$pointer',
                'connectFromField' => 'pointer',
                'connectToField' => 'parent',
                'as' => 'children',
                'maxDepth' => 0,
                'restrictSearchWithMatch' => $this->prepareChildrenFilter(NodeInterface::DELETED_ONLY),
            ]],
            ['$addFields' => [
                'size' => [
                    '$cond' => [
                        'if' => ['$eq' => ['$directory', true]],
                        'then' => ['$size' => '$children'],
                        'else' => '$size',
                    ],
                ],
            ]],
            ['$project' => ['children' => 0, 'parents' => 0]],
            ['$group' => ['_id' => null, 'total' => ['$sum' => 1]]],
        ];

        return $this->executeAggregation($query, $offset, $limit);
    }

    /**
     * Find nodes with custom filter recursive.
     */
    public function findNodesByFilterRecursiveChildren(array $parent_filter, int $deleted, ?int $offset = null, ?int $limit = null, ?Collection $parent = null): Generator
    {
        $query = [
            ['$match' => $parent_filter],
            ['$graphLookup' => [
                'from' => 'storage',
                'startWith' => '$pointer',
                'connectFromField' => 'pointer',
                'connectToField' => 'parent',
                'as' => 'children',
                'maxDepth' => 0,
                'restrictSearchWithMatch' => $this->prepareChildrenFilter($deleted),
            ]],
            ['$addFields' => [
                'size' => [
                    '$cond' => [
                        'if' => ['$eq' => ['$directory', true]],
                        'then' => ['$size' => '$children'],
                        'else' => '$size',
                    ],
                ],
            ]],
            ['$project' => ['children' => 0]],
            ['$group' => ['_id' => null, 'total' => ['$sum' => 1]]],
        ];

        return $this->executeAggregation($query, $offset, $limit, $parent);
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

        return $this->executeAggregation($query, $offset, $limit, $collection);
    }

    /**
     * Get custom filtered children.
     *
     * @deprecated
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

        return $this->findNodesByFilterRecursiveChildren($stored_filter, $deleted, $offset, $limit);
    }

    /**
     * Init node.
     */
    public function initNode(array $node, ?Collection $parent = null): NodeInterface
    {
        $id = $node['_id'];

        if (isset($node['shared']) && true === $node['shared'] && null !== $this->user && $node['owner'] != $this->user->getId()) {
            $node = $this->findReferenceNode($node);
        }

        if (isset($node['parent'])) {
            if ($parent === null || $parent->getId() != $node['parent']) {
                $parent = $this->findNodeById($node['parent']);
            }
        } elseif ($node['_id'] !== null) {
            $parent = $this->getRoot();
        }

        if (!array_key_exists('directory', $node)) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        $instance = $this->node_factory->build($this, $node, $parent);
        $loaded = isset($this->cache[(string) $node['_id']]);

        if ($loaded === false) {
            $this->cache[(string) $node['_id']] = $instance;
        }

        if (!$this->acl->isAllowed($instance, 'r')) {
            if ($instance->isReference()) {
                $instance->delete(true);
            }

            throw new ForbiddenException('not allowed to access node', ForbiddenException::NOT_ALLOWED_TO_ACCESS);
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

    /**
     * Prepare children filter.
     */
    protected function prepareChildrenFilter(int $deleted)
    {
        $deleted_filter = [];
        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $deleted_filter['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $deleted_filter['deleted'] = ['$type' => 9];
        }

        $query = ['_id' => ['$exists' => true]];

        if ($this->user !== null) {
            $query = [
                '$or' => [
                    [
                        'owner' => $this->user->getId(),
                    ],
                    [
                        'acl' => ['$exists' => false],
                    ],
                    [
                        'acl.id' => (string) $this->user->getId(),
                        'type' => 'user',
                    ],
                    [
                        'acl.id' => ['$in' => array_map('strval', $this->user->getGroups())],
                        'type' => 'group',
                    ],
                ],
            ];
        }

        if (count($deleted_filter) > 0) {
            $query = ['$and' => [$deleted_filter, $query]];
        }

        return $query;
    }

    /**
     * Execute complex aggregation.
     */
    protected function executeAggregation(array $query, ?int $offset = null, ?int $limit = null, ?Collection $parent = null): Generator
    {
        $result = $this->db->storage->aggregate($query);

        $total = 0;
        $result = iterator_to_array($result);
        if (count($result) > 0) {
            $total = $result[0]['total'];
        }

        array_pop($query);

        $offset !== null ? $query[] = ['$skip' => $offset] : false;
        $limit !== null ? $query[] = ['$limit' => $limit] : false;
        $result = $this->db->storage->aggregate($query);

        foreach ($result as $node) {
            try {
                yield $this->initNode($node, $parent);
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
                throw new Exception\NotFound('no share node for reference node '.$node['reference'].' found', Exception\NotFound::SHARE_NOT_FOUND);
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
                throw new Exception\NotFound('no share reference for node '.$node['_id'].' found', Exception\NotFound::REFERENCE_NOT_FOUND);
            }
        }

        return $result;
    }
}
