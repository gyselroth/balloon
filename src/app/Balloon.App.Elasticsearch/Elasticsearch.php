<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Elasticsearch
{
    /**
     * ES server.
     *
     * @var array
     */
    protected $es_server = ['http://localhost:9200'];

    /**
     * ES index.
     *
     * @var string
     */
    protected $es_index = 'balloon';

    /**
     * ES client.
     *
     * @var Elasticsearch
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

    // Constructor
    public function __construct(Server $server, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->setOptions($config);
        $this->logger = $logger;
        $this->user = $server->getIdentity();
        $this->fs = $server->getFilesystem();
    }

    /**
     * Set options.
     *
     * @return Elasticsearch
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'server':
                    $this->es_server = (array) $value;

                break;
                case 'index':
                    $this->es_index = (string) $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Search.
     *
     * @param int $skip
     * @param int $limit
     * @param int $total
     */
    public function search(array $query, int $deleted = NodeInterface::DELETED_INCLUDE, ?int $skip = null, ?int $limit = null, ?int &$total = null): Generator
    {
        $result = $this->executeQuery($query, $skip, $limit);

        if (isset($result['error'])) {
            throw new Exception\InvalidQuery('failed search index, query failed');
        }

        $this->logger->debug('elasticsearch query executed with ['.$result['hits']['total'].'] hits', [
            'category' => get_class($this),
            'params' => [
                'took' => $result['took'],
                'timed_out' => $result['timed_out'],
                '_shards' => $result['_shards'],
                'max_score' => $result['hits']['max_score'],
                'hits' => $result['hits']['total'],
            ],
        ]);

        $total = $result['hits']['total'];

        $nodes = [];
        foreach ($result['hits']['hits'] as $node) {
            if ('storage' === $node['_type']) {
                $nodes[$node['_id']] = $node;
            } elseif ('fs' === $node['_type']) {
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
     * Get index name.
     */
    public function getIndex(): string
    {
        return $this->es_index;
    }

    /**
     * Get es client.
     */
    public function getEsClient(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        return $this->client = ClientBuilder::create()
            ->setHosts($this->es_server)
            ->build();
    }

    /**
     * Search.
     */
    protected function executeQuery(array $query, ?int $skip = null, ?int $limit = null): array
    {
        $shares = $this->user->getShares();
        $bool = $query['body']['query'];

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
            $share_filter['bool']['should'][]['term']['reference'] = $share;
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
        $query['index'] = $this->es_index;
        $query['from'] = $skip;
        $query['size'] = $limit;

        $this->logger->debug('prepared elasticsearch query', [
            'category' => get_class($this),
            'params' => $query,
        ]);

        $result = $this->getEsClient()->search($query);

        if (null === $result) {
            $this->logger->error('failed search elastic, query returned NULL', [
                'category' => get_class($this),
            ]);

            throw new Exception\InvalidQuery('general search error occured');
        }

        return $result;
    }
}
