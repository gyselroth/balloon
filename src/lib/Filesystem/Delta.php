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
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Helper;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Cursor;

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
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

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
    public function __construct(Filesystem $fs, Database $db, AttributeDecorator $decorator)
    {
        $this->fs = $fs;
        $this->db = $db;
        $this->user = $fs->getUser();
        $this->decorator = $decorator;
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
    public function add(string $event, NodeInterface $node, array $context): ObjectId
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
            return base64_encode('delta|0|0|0|'.new UTCDateTime());
        }

        return $cursor = base64_encode('delta|0|0|'.$last['_id'].'|'.$last['timestamp']);
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
        /*$attributes = array_merge(
            ['id', 'directory', 'deleted',  'path', 'changed', 'created', 'owner'],
            $attributes
        );*/

        $cursor = $this->decodeCursor($cursor);

        if (null === $cursor || 'initial' === $cursor[0]) {
            return $this->buildFeedFromCurrentState($cursor, $limit, $attributes, $node);
        }

        try {
            if (0 === $cursor[3]) {
                $filter = $this->getDeltaFilter();
            } else {
                //check if delta entry actually exists
                if (0 === $this->db->delta->count(['_id' => new ObjectId($cursor[3])])) {
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
                        $member_attrs = $this->decorator->decorate($share_member, $attributes);
                        $list[$member_attrs['path']] = $member_attrs;
                    }
                }

                $fields = $this->decorator->decorate($log_node, $attributes);

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
            } catch (ForbiddenException $e) {
                //no delta entriy for a node where we do not have access to
            } catch (\Exception $e) {
                try {
                    if (null === $log['parent']) {
                        $path = DIRECTORY_SEPARATOR.$log['name'];
                    } else {
                        $parent = $this->fs->findNodeById($log['parent']);
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
     * @return Cursor
     */
    public function getEventLog(int $limit = 100, int $skip = 0, ?NodeInterface $node = null): Cursor
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

        return $result;
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

    /**
     * Get children with custom filter.
     *
     * @param array         $filter
     * @param array         $attributes
     * @param int           $limit
     * @param string        $cursor
     * @param bool          $has_more
     * @param NodeInterface $parent
     *
     * @return array
     */
    protected function findNodeAttributesWithCustomFilter(
        ?array $filter = null,
        array $attributes = ['id'],
        ?int $limit = null,
        ?int &$cursor = null,
        ?bool &$has_more = null,
        ?NodeInterface $parent = null
    ) {
        $list = [];

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

            $list[] = $this->decorator->decorate($node/*, $attributes*/);
        }

        $has_more = ($left - $count) > 0;

        return $list;
    }
}
