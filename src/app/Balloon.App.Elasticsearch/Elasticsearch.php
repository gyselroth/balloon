<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use Elasticsearch\Client;
use Generator;
use Psr\Log\LoggerInterface;

class Elasticsearch
{
    /**
     * ES client.
     *
     * @var Client
     */
    protected $client;

    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->user = $server->getIdentity();
        $this->fs = $server->getFilesystem();
    }

    /**
     * Search.
     */
    public function search(array $query, int $deleted = NodeInterface::DELETED_INCLUDE, ?int $skip = null, ?int $limit = null, ?int &$total = null): Generator
    {
        $result = $this->executeQuery($query, $skip, $limit);

        $this->logger->debug('elasticsearch query executed with ['.$result['hits']['total']['value'].'] hits', [
            'category' => static::class,
            'params' => [
                'took' => $result['took'],
                'timed_out' => $result['timed_out'],
                '_shards' => $result['_shards'],
                'max_score' => $result['hits']['max_score'],
                'hits' => $result['hits']['total'],
            ],
        ]);

        $total = $result['hits']['total']['value'];

        $nodes = [];
        foreach ($result['hits']['hits'] as $node) {
            if ('nodes' === $node['_index']) {
                $nodes[$node['_id']] = $node;
            } elseif ('blobs' === $node['_index']) {
                if (isset($node['_source']['metadata']['ref'])) {
                    foreach ($node['_source']['metadata']['ref'] as $blob) {
                        $nodes[$blob['id']] = $blob;
                    }
                }

                if (isset($node['_source']['metadata']['share_ref'])) {
                    foreach ($node['_source']['metadata']['share_ref'] as $blob) {
                        $nodes[$blob['id']] = $blob;
                    }
                }
            }
        }

        return $this->fs->findNodesById(array_keys($nodes), null, $deleted);
    }

    /**
     * Search.
     */
    protected function executeQuery(array $query, ?int $skip = null, ?int $limit = null): array
    {
        $shares = $this->user->getShares();
        $bool = [];

        if (isset($query['body']['query'])) {
            $bool = $query['body']['query'];
        }

        $filter1 = [];
        $filter1['bool']['should'][]['term']['owner'] = (string) $this->user->getId();
        $filter1['bool']['should'][]['term']['metadata.ref.owner'] = (string) $this->user->getId();

        $share_filter = [
            'bool' => [
                'should' => [],
            ],
        ];

        foreach ($shares as $share) {
            $share = (string) $share;
            $share_filter['bool']['should'][]['term']['metadata.share_ref.share'] = $share;
            $share_filter['bool']['should'][]['term']['shared'] = $share;
            $share_filter['bool']['minimum_should_match'] = 1;
        }

        if (count($share_filter['bool']['should']) >= 1) {
            $rights_filter = [];
            $rights_filter['bool']['should'][] = $share_filter;
            $rights_filter['bool']['should'][] = $filter1;
            $rights_filter['bool']['minimum_should_match'] = 1;
        } else {
            $rights_filter = $filter1;
        }

        $query['body']['query']['bool'] = [
            'must' => [
                $bool,
                $rights_filter,
            ],
        ];

        $query['_source'] = ['metadata.*', '_id', 'owner'];
        $query['from'] = $skip;
        $query['size'] = $limit;

        $this->logger->debug('prepared elasticsearch query', [
            'category' => static::class,
            'params' => $query,
        ]);

        $result = $this->client->search($query);

        if ($result === null || $result['_shards']['failed'] > 0) {
            throw new Exception\InvalidQuery('elasticsearch query failed');
        }

        return $result;
    }
}
