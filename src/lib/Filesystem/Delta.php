<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class Delta
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Db.
     *
     * @var Database
     */
    protected $db;

    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Client.
     *
     * @var array
     */
    protected $client = [
        'type' => null,
        'app' => null,
        'v' => null,
        'hostname' => null,
    ];

    /**
     * Initialize delta.
     *
     * @param Filesystem $fs
     */
    public function __construct(Filesystem $fs, Database $db)
    {
        $this->fs = $fs;
        $this->db = $db;
        $this->user = $fs->getUser();
        $this->parseClient();
    }

    /**
     * Add delta event.
     *
     * @param string        $name
     * @param NodeInterface $node
     * @param array         $options
     *
     * @return ObjectId
     */
    public function add(string $event, NodeInterface $node, array $context = []): ObjectId
    {
        $context['operation'] = $event;
        $context['owner'] = $this->getEventOwner($node);
        $context['name'] = $node->getName();
        $context['node'] = $node->getId();

        if ($node->isShareMember()) {
            $context['share'] = $node->getShareId();
        }

        $context['timestamp'] = new UTCDateTime();
        $context['client'] = $this->client;

        $result = $this->db->delta->insertOne($context);

        return $result->getInsertedId();
    }

    /**
     * Build a single dimension array with all nodes.
     *
     * @param array         $cursor
     * @param int           $limit
     * @param NodeInterface $node
     *
     * @return array
     */
    public function buildFeedFromCurrentState(?array $cursor = null, int $limit = 100, ?NodeInterface $node = null): array
    {
        $current_cursor = 0;
        $filter = ['$and' => [
            ['$or' => [
                ['shared' => [
                    '$in' => $this->user->getShares(),
                ]],
                [
                    'shared' => ['$type' => 8],
                    'owner' => $this->user->getId(),
                ],
            ]],
            ['deleted' => false],
        ]];

        if (is_array($cursor)) {
            $current_cursor = $cursor[1];
        }

        $children = $this->findNodeAttributesWithCustomFilter(
            $filter,
            $limit,
            $current_cursor,
            $has_more,
            $node
        );

        $reset = false;

        if (null === $cursor) {
            $last = $this->getLastRecord();
            if (null === $last) {
                $delta_id = 0;
                $ts = new UTCDateTime();
            } else {
                $delta_id = $last['_id'];
                $ts = $last['timestamp'];
            }

            $reset = true;
            if (false === $has_more) {
                $cursor = base64_encode('delta|0|0|'.$delta_id.'|'.$ts);
            } else {
                $cursor = base64_encode('initial|'.$current_cursor.'|'.end($children)->getId().'|'.$delta_id.'|'.$ts);
            }
        } else {
            if (false === $has_more) {
                $cursor = base64_encode('delta|0|0|'.$cursor[3].'|'.$cursor[4]);
            } else {
                $cursor = base64_encode('initial|'.$current_cursor.'|'.end($children)->getId().'|'.$cursor[3].'|'.$cursor[4]);
            }
        }

        return [
            'reset' => $reset,
            'cursor' => $cursor,
            'has_more' => $has_more,
            'nodes' => $children,
        ];
    }

    /**
     * Get last delta event.
     *
     * @param NodeInterface $node
     *
     * @return array
     */
    public function getLastRecord(?NodeInterface $node = null): ?array
    {
        $filter = $this->getDeltaFilter();

        if (null !== $node) {
            $filter = [
                '$and' => [
                    ['node' => $node->getId()],
                    $filter,
                ],
            ];
        }

        $cursor = $this->db->delta->find($filter, [
            'sort' => ['timestamp' => -1],
            'limit' => 1,
        ]);

        $last = $cursor->toArray();

        return array_shift($last);
    }

    /**
     * Get last cursor.
     *
     * @param NodeInterface $node
     *
     * @return string
     */
    public function getLastCursor(?NodeInterface $node = null): string
    {
        $filter = $this->getDeltaFilter();

        if (null !== $node) {
            $filter = [
                '$and' => [
                    ['node' => $node->getId()],
                    $filter,
                ],
            ];
        }

        $count = $this->db->delta->count($filter);
        $last = $this->getLastRecord($node);

        if (null === $last) {
            return base64_encode('delta|0|0|0|'.new UTCDateTime());
        }

        return $cursor = base64_encode('delta|0|0|'.$last['_id'].'|'.$last['timestamp']);
    }

    /**
     * Get delta feed with changes and cursor.
     *
     * @param string        $cursor
     * @param int           $limit
     * @param NodeInterface $node
     *
     * @return array
     */
    public function getDeltaFeed(?string $cursor = null, int $limit = 250, ?NodeInterface $node = null): array
    {
        $query = $this->getDeltaQuery($cursor, $limit, $node);
        if (array_key_exists('nodes', $query)) {
            return $query;
        }

        $delta = [];

        foreach ($query['result'] as $log) {
            if (false === $query['has_more']) {
                $query['last_id'] = (string) $log['_id'];
                $query['last_ts'] = (string) $log['timestamp'];
            }

            try {
                $log_node = $this->fs->findNodeById($log['node'], null, NodeInterface::DELETED_EXCLUDE);
                if (null !== $node && !$node->isSubNode($log_node)) {
                    continue;
                }

                //include share children after a new reference was added, otherwise the children would be lost if the cursor is newer
                //than the create timestamp of the share reference
                if ('addCollectionReference' === $log['operation'] && $log_node->isReference()) {
                    $members = $this->fs->findNodesByFilter([
                        'shared' => $log_node->getShareId(),
                        'deleted' => false,
                    ]);

                    foreach ($members as $share_member) {
                        $delta[$share_member->getPath()] = $share_member;
                    }
                }

                if (array_key_exists('previous', $log)) {
                    if (array_key_exists('parent', $log['previous'])) {
                        if ($log['previous']['parent'] === null) {
                            $previous_path = DIRECTORY_SEPARATOR.$log['name'];
                        } else {
                            $parent = $this->fs->findNodeById($log['previous']['parent']);
                            $previous_path = $parent->getPath().DIRECTORY_SEPARATOR.$log['name'];
                        }
                    } elseif (array_key_exists('name', $log['previous'])) {
                        if (null === $log['parent']) {
                            $previous_path = DIRECTORY_SEPARATOR.$log['previous']['name'];
                        } else {
                            $parent = $this->fs->findNodeById($log['parent']);
                            $previous_path = $parent->getPath().DIRECTORY_SEPARATOR.$log['previous']['name'];
                        }
                    } else {
                        $delta[$log_node->getPath()] = $log_node;

                        continue;
                    }

                    $deleted_node = [
                        'id' => (string) $log['node'],
                        'deleted' => true,
                        'created' => null,
                        'changed' => $log['timestamp'],
                        'path' => $previous_path,
                        'directory' => $log_node instanceof Collection,
                    ];

                    $delta[$previous_path] = $deleted_node;
                    $delta[$log_node->getPath()] = $log_node;
                } else {
                    $delta[$log_node->getPath()] = $log_node;
                }
            } catch (ForbiddenException $e) {
                //no delta entriy for a node where we do not have access to
            } catch (\Exception $e) {
                $deleted = $this->getDeletedNodeDelta($log);

                if ($deleted !== null) {
                    $delta[$deleted['path']] = $deleted;
                }
            }
        }

        $cursor = base64_encode('delta|'.$query['cursor'].'|0|'.$query['last_id'].'|'.$query['last_ts']);

        return [
            'reset' => false,
            'cursor' => $cursor,
            'has_more' => $query['has_more'],
            'nodes' => array_values($delta),
        ];
    }

    /**
     * Get event log.
     *
     * @param int           $limit
     * @param int           $skip
     * @param NodeInterface $node
     *
     * @return iterable
     */
    public function getEventLog(int $limit = 100, int $skip = 0, ?NodeInterface $node = null, ?int &$total = null): Iterable
    {
        $filter = $this->getDeltaFilter();

        if (null !== $node) {
            $old = $filter;
            $filter = ['$and' => [[
                'node' => $node->getId(),
            ],
            $old, ]];
        }

        $total = $this->db->delta->count($filter);
        $result = $this->db->delta->find($filter, [
            'sort' => ['_id' => -1],
            'skip' => $skip,
            'limit' => $limit,
        ]);

        return $result;
    }

    /**
     * Get delta feed filter.
     *
     * @param array $cursor
     *
     * @return array
     */
    protected function buildDeltaFeedFilter(array $cursor, int $limit, ?NodeInterface $node): array
    {
        if (0 === $cursor[3]) {
            return $this->getDeltaFilter();
        }

        if (0 === $this->db->delta->count(['_id' => new ObjectId($cursor[3])])) {
            return $this->buildFeedFromCurrentState(null, $limit, $node);
        }

        $filter = $this->getDeltaFilter();

        return [
            '$and' => [
                ['timestamp' => ['$gte' => new UTCDateTime($cursor[4])]],
                ['_id' => ['$gt' => new ObjectId($cursor[3])]],
                $filter,
            ],
        ];
    }

    /**
     * Get delta feed with changes and cursor.
     *
     * @param string        $cursor
     * @param int           $limit
     * @param NodeInterface $node
     *
     * @return array
     */
    protected function getDeltaQuery(?string $cursor = null, int $limit = 250, ?NodeInterface $node = null): array
    {
        $cursor = $this->decodeCursor($cursor);

        if (null === $cursor || 'initial' === $cursor[0]) {
            return $this->buildFeedFromCurrentState($cursor, $limit, $node);
        }

        try {
            $filter = $this->buildDeltaFeedFilter($cursor, $limit, $node);

            $result = $this->db->delta->find($filter, [
                'skip' => (int) $cursor[1],
                'limit' => (int) $limit,
                'sort' => ['timestamp' => 1],
            ]);

            $left = $this->db->delta->count($filter, [
                'skip' => (int) $cursor[1],
                'sort' => ['timestamp' => 1],
            ]);

            $result = $result->toArray();
            $count = count($result);
        } catch (\Exception $e) {
            return $this->buildFeedFromCurrentState(null, $limit, $node);
        }

        $position = $cursor[1] += $limit;
        $has_more = ($left - $count) > 0;
        if (false === $has_more) {
            $position = 0;
        }

        return [
            'result' => $result,
            'has_more' => $has_more,
            'cursor' => $position,
            'last_id' => $cursor[3],
            'last_ts' => $cursor[4],
        ];
    }

    /**
     * Get delta event for a (forced) deleted node.
     *
     * @param array $event
     *
     * @return array
     */
    protected function getDeletedNodeDelta(array $event): ?array
    {
        try {
            if (null === $event['parent']) {
                $path = DIRECTORY_SEPARATOR.$event['name'];
            } else {
                $parent = $this->fs->findNodeById($event['parent']);
                $path = $parent->getPath().DIRECTORY_SEPARATOR.$event['name'];
            }

            $entry = [
                'id' => (string) $event['node'],
                'deleted' => true,
                'created' => null,
                'changed' => $event['timestamp'],
                'path' => $path,
            ];

            if ('deleteCollection' === substr($event['operation'], 0, 16)) {
                $entry['directory'] = true;
            } elseif ('deleteFile' === substr($event['operation'], 0, 10)) {
                $entry['directory'] = false;
            }

            return $entry;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Init.
     *
     * @return bool
     */
    protected function parseClient(): bool
    {
        if (PHP_SAPI === 'cli') {
            $this->client = [
                'type' => 'system',
                'app' => 'system',
                'v' => null,
                'hostname' => null,
            ];
        } else {
            if (isset($_SERVER['HTTP_X_CLIENT'])) {
                $parts = explode('|', strip_tags($_SERVER['HTTP_X_CLIENT']));
                $count = count($parts);

                if (3 === $count) {
                    $this->client['v'] = $parts[1];
                    $this->client['hostname'] = $parts[2];
                } elseif (2 === $count) {
                    $this->client['v'] = $parts[1];
                }

                $this->client['app'] = $parts[0];
            }

            if (isset($_SERVER['PATH_INFO'])) {
                $parts = explode('/', $_SERVER['PATH_INFO']);
                if (count($parts) >= 2) {
                    $this->client['type'] = $parts[1];
                }
            }
        }

        return true;
    }

    /**
     * Get Event owner id.
     *
     * @param NodeInterface $node
     *
     * @return ObjectId
     */
    protected function getEventOwner(NodeInterface $node): ObjectId
    {
        $user = $node->getFilesystem()->getUser();
        if (null === $user) {
            return $node->getOwner();
        }

        return $user->getId();
    }

    /**
     * Decode cursor.
     *
     * @param string $cursor
     *
     * @return array
     */
    protected function decodeCursor(?string $cursor): ?array
    {
        if (null === $cursor) {
            return null;
        }

        $cursor = base64_decode($cursor, true);
        if (false === $cursor) {
            return null;
        }

        $cursor = explode('|', $cursor);
        if (5 !== count($cursor)) {
            return null;
        }
        $cursor[1] = (int) $cursor[1];

        return $cursor;
    }

    /**
     * Get delta filter for db query.
     *
     * @return array
     */
    protected function getDeltaFilter(): array
    {
        return [
            '$or' => [
                ['share' => [
                    '$in' => $this->user->getShares(),
                ]], [
                    'owner' => $this->user->getId(),
                ],
            ],
        ];
    }

    /**
     * Get children with custom filter.
     *
     * @param array         $filter
     * @param int           $limit
     * @param string        $cursor
     * @param bool          $has_more
     * @param NodeInterface $parent
     *
     * @return array
     */
    protected function findNodeAttributesWithCustomFilter(
        ?array $filter = null,
        ?int $limit = null,
        ?int &$cursor = null,
        ?bool &$has_more = null,
        ?NodeInterface $parent = null
    ) {
        $delta = [];

        $result = $this->db->storage->find($filter, [
            'skip' => $cursor,
            'limit' => $limit,
        ]);

        $left = $this->db->storage->count($filter, [
            'skip' => $cursor,
        ]);

        $result = $result->toArray();
        $count = count($result);
        $has_more = ($left - $count) > 0;

        foreach ($result as $node) {
            ++$cursor;

            try {
                $node = $this->fs->initNode($node);

                if (null !== $parent && !$parent->isSubNode($node)) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }

            $delta[] = $node;
        }

        return $delta;
    }
}
