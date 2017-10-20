<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\Filesystem;
use Balloon\Filesystem\Delta\Exception;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Helper;
use Balloon\Server\User;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;

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
     * @var \MongoDB\Database
     */
    protected $db;

    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Initialize delta.
     *
     * @param Filesystem $fs
     */
    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
        $this->db = $fs->getDatabase();
        $this->user = $fs->getUser();
    }

    /**
     * Add delta event.
     *
     * @param array $options
     *
     * @return bool
     */
    public function add(array $options): bool
    {
        if (!$this->isValidDeltaEvent($options)) {
            throw new Exception('invalid delta structuce given');
        }

        if (!array_key_exists('timestamp', $options)) {
            $options['timestamp'] = new UTCDateTime();
        }

        $result = $this->db->delta->insertOne($options);

        return $result->isAcknowledged();
    }

    /**
     * Build a single dimension array with all nodes.
     *
     * @param array         $cursor
     * @param int           $limit
     * @param array         $attributes
     * @param NodeInterface $node
     *
     * @return array
     */
    public function buildFeedFromCurrentState(?array $cursor = null, int $limit = 100, array $attributes = [], ?NodeInterface $node = null): array
    {
        $current_cursor = 0;
        $filter = ['$and' => [
            ['$or' => [
                ['shared' => [
                    '$in' => $this->user->getShares(),
                ]],
                ['owner' => $this->user->getId()],
            ]],
            ['deleted' => false],
        ]];

        if (is_array($cursor)) {
            $current_cursor = $cursor[1];
        }

        $children = $this->fs->findNodeAttributesWithCustomFilter(
            $filter,
            $attributes,
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
                $cursor = base64_encode('initial|'.$current_cursor.'|'.end($children)['id'].'|'.$delta_id.'|'.$ts);
            }
        } else {
            if (false === $has_more) {
                $cursor = base64_encode('delta|0|0|'.$cursor[3].'|'.$cursor[4]);
            } else {
                $cursor = base64_encode('initial|'.$current_cursor.'|'.end($children)['id'].'|'.$cursor[3].'|'.$cursor[4]);
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
            $cursor = base64_encode('delta|0|0|0|'.new UTCDateTime());
        } else {
            $cursor = base64_encode('delta|0|0|'.$last['_id'].'|'.$last['timestamp']);
        }

        return $cursor;
    }

    /**
     * Get delta feed with changes and cursor.
     *
     * @param string        $cursor
     * @param int           $limit
     * @param array         $attributes
     * @param NodeInterface $node
     *
     * @return array
     */
    public function getDeltaFeed(?string $cursor = null, int $limit = 250, array $attributes = [], ?NodeInterface $node = null): array
    {
        $this->user->findNewShares();

        $attributes = array_merge(
            ['id', 'directory', 'deleted',  'path', 'changed', 'created', 'owner'],
            $attributes
        );

        $cursor = $this->decodeCursor($cursor);

        if (null === $cursor || 'initial' === $cursor[0]) {
            return $this->buildFeedFromCurrentState($cursor, $limit, $attributes, $node);
        }

        try {
            if (0 === $cursor[3]) {
                $filter = $this->getDeltaFilter();
            } else {
                //check if delta entry actually exists
                if (0 === $this->db->delta->count(['_id' => new ObjectID($cursor[3])])) {
                    return $this->buildFeedFromCurrentState(null, $limit, $attributes, $node);
                }

                $filter = $this->getDeltaFilter();
                $filter = [
                    '$and' => [
                        ['timestamp' => ['$gte' => new UTCDateTime($cursor[4])]],
                        ['_id' => ['$gt' => new ObjectId($cursor[3])]],
                        $filter,
                    ],
                ];
            }

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
            $list = [];
            $last_id = $cursor[3];
            $last_ts = $cursor[4];
        } catch (\Exception $e) {
            return $this->buildFeedFromCurrentState(null, $limit, $attributes, $node);
        }

        $cursor = $cursor[1] += $limit;
        $has_more = ($left - $count) > 0;
        if (false === $has_more) {
            $cursor = 0;
        }

        foreach ($result as $log) {
            if (false === $has_more) {
                $last_id = (string) $log['_id'];
                $last_ts = (string) $log['timestamp'];
            }

            try {
                $log_node = $this->fs->findNodeWithId($log['node'], null, NodeInterface::DELETED_EXCLUDE);
                if (null !== $node && !$node->isSubNode($log_node)) {
                    continue;
                }

                //include share children after a new reference was added, otherwise the children would be lost if the cursor is newer
                //than the create timestamp of the share reference
                if ('addCollectionReference' === $log['operation'] && $log_node->isReference()) {
                    foreach ($this->fs->findNodesWithCustomFilter(['shared' => $log_node->getShareId()]) as $share_member) {
                        $member_attrs = $share_member->getAttributes($attributes);
                        $list[$member_attrs['path']] = $member_attrs;
                    }
                }

                $fields = $log_node->getAttributes($attributes);

                if (array_key_exists('previous', $log)) {
                    if (array_key_exists('parent', $log['previous'])) {
                        if ($log['previous']['parent'] === null) {
                            $previous_path = DIRECTORY_SEPARATOR.$log['name'];
                        } else {
                            $parent = $this->fs->findNodeWithId($log['previous']['parent']);
                            $previous_path = $parent->getPath().DIRECTORY_SEPARATOR.$log['name'];
                        }
                    } elseif (array_key_exists('name', $log['previous'])) {
                        if (null === $log['parent']) {
                            $previous_path = DIRECTORY_SEPARATOR.$log['previous']['name'];
                        } else {
                            $parent = $this->fs->findNodeWithId($log['parent']);
                            $previous_path = $parent->getPath().DIRECTORY_SEPARATOR.$log['previous']['name'];
                        }
                    } else {
                        $list[$fields['path']] = $fields;

                        continue;
                    }

                    $deleted_node = [
                        'id' => (string) $log['node'],
                        'deleted' => true,
                        'created' => null,
                        'changed' => Helper::DateTimeToUnix($log['timestamp']),
                        'path' => $previous_path,
                        'directory' => $fields['directory'],
                    ];

                    $list[$previous_path] = $deleted_node;
                    $list[$fields['path']] = $fields;
                } else {
                    $list[$fields['path']] = $fields;
                }
            } catch (\Exception $e) {
                try {
                    if (null === $log['parent']) {
                        $path = DIRECTORY_SEPARATOR.$log['name'];
                    } else {
                        $parent = $this->fs->findNodeWithId($log['parent']);
                        $path = $parent->getPath().DIRECTORY_SEPARATOR.$log['name'];
                    }

                    $entry = [
                        'id' => (string) $log['node'],
                        'deleted' => true,
                        'created' => null,
                        'changed' => Helper::DateTimeToUnix($log['timestamp']),
                        'path' => $path,
                    ];

                    if ('deleteCollection' === substr($log['operation'], 0, 16)) {
                        $entry['directory'] = true;
                    } elseif ('deleteFile' === substr($log['operation'], 0, 10)) {
                        $entry['directory'] = false;
                    }

                    $list[$path] = $entry;
                } catch (\Exception $e) {
                }
            }
        }

        $cursor = base64_encode('delta|'.$cursor.'|0|'.$last_id.'|'.$last_ts);

        return [
            'reset' => false,
            'cursor' => $cursor,
            'has_more' => $has_more,
            'nodes' => array_values($list),
        ];
    }

    /**
     * Get event log.
     *
     * @param int           $limit
     * @param int           $skip
     * @param NodeInterface $node
     *
     * @return array
     */
    public function getEventLog(int $limit = 100, int $skip = 0, ?NodeInterface $node = null): array
    {
        $filter = $this->getDeltaFilter();

        if (null !== $node) {
            $old = $filter;
            $filter = ['$and' => [[
                'node' => $node->getId(),
            ],
            $old, ]];
        }

        $result = $this->db->delta->find($filter, [
            'sort' => ['_id' => -1],
            'skip' => $skip,
            'limit' => $limit,
        ]);

        $client = [
            'type' => null,
            'app' => null,
            'v' => null,
            'hostname' => null,
        ];

        $events = [];
        foreach ($result as $log) {
            $id = (string) $log['_id'];
            $events[$id] = [
                'event' => $id,
                'timestamp' => Helper::DateTimeToUnix($log['timestamp']),
                'operation' => $log['operation'],
                'name' => $log['name'],
                'client' => isset($log['client']) ? $log['client'] : $client,
            ];

            if (isset($log['previous'])) {
                $events[$id]['previous'] = $log['previous'];

                if (array_key_exists('parent', $events[$id]['previous'])) {
                    if ($events[$id]['previous']['parent'] === null) {
                        $events[$id]['previous']['parent'] = [
                            'id' => null,
                            'name' => null,
                        ];
                    } else {
                        try {
                            $node = $this->fs->findNodeWithId($events[$id]['previous']['parent'], null, NodeInterface::DELETED_INCLUDE);
                            $events[$id]['previous']['parent'] = [
                                'id' => (string) $node->getId(),
                                'name' => $node->getName(),
                            ];
                        } catch (\Exception $e) {
                            $events[$id]['previous']['parent'] = null;
                        }
                    }
                }
            } else {
                $events[$id]['previous'] = null;
            }

            try {
                $node = $this->fs->findNodeWithId($log['node'], null, NodeInterface::DELETED_INCLUDE);
                $events[$id]['node'] = [
                    'id' => (string) $node->getId(),
                    'name' => $node->getName(),
                    'deleted' => $node->isDeleted(),
                ];
            } catch (\Exception $e) {
                $events[$id]['node'] = null;
            }

            try {
                if (null === $log['parent']) {
                    $events[$id]['parent'] = [
                        'id' => null,
                        'name' => null,
                    ];
                } else {
                    $node = $this->fs->findNodeWithId($log['parent'], null, NodeInterface::DELETED_INCLUDE);
                    $events[$id]['parent'] = [
                        'id' => (string) $node->getId(),
                        'name' => $node->getName(),
                        'deleted' => $node->isDeleted(),
                    ];
                }
            } catch (\Exception $e) {
                $events[$id]['parent'] = null;
            }

            try {
                $user = $this->fs->getServer()->getUserById($log['owner']);
                $events[$id]['user'] = [
                    'id' => (string) $user->getId(),
                    'username' => $user->getUsername(),
                ];
            } catch (\Exception $e) {
                $events[$id]['user'] = null;
            }

            try {
                if (isset($log['share']) && false === $log['share'] || !isset($log['share'])) {
                    $events[$id]['share'] = null;
                } else {
                    $node = $this->fs->findNodeWithId($log['share'], null, NodeInterface::DELETED_INCLUDE);
                    $events[$id]['share'] = [
                        'id' => (string) $node->getId(),
                        'name' => $node->getName(),
                        'deleted' => $node->isDeleted(),
                    ];
                }
            } catch (\Exception $e) {
                $events[$id]['share'] = null;
            }
        }

        return array_values($events);
    }

    /**
     * Verify delta structure.
     *
     * @param array $options
     *
     * @return bool
     */
    protected function isValidDeltaEvent(array $options): bool
    {
        if (!array_key_exists('operation', $options)) {
            return false;
        }

        return array_key_exists('owner', $options) || array_key_exists('share', $options);
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
}
