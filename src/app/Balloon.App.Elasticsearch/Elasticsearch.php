<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\App;

use Balloon\App\AppInterface;
use Balloon\Server\User;
use Elasticsearch\ClientBuilder;
use Balloon\App\Elasticsearch\App;
use Micro\Http\Router\Route;
use Balloon\App\Elasticsearch\Api\v1\Search as Api;
use Micro\Http\Router;
use Balloon\Server;
use Psr\Log\LoggerInterface;
use Elasticsearch\Client;

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
     * ES client
     *
     * @var Elasticsearch
     */
    protected $client;

    /**
     * Server
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /*
     * Constructor
     *
     * @param Router
     */
    public function __construct(Server $server, LoggerInterface $logger, ?Iterable $config=null)
    {
        $this->setOptions($config);
        $this->logger = $logger;
        $this->server = $server;
    }

    /**
     * Get es client
     *
     * @return Elasticsearch
     */
    protected function getEsClient(): Client
    {
        if($this->client instanceof Client) {
            return $this->client;
        }

        return $this->client = ClientBuilder::create()
            ->setHosts($this->es_server)
            ->build();
    }

    /**
     * Set options.
     *
     * @var iterable
     */
    public function setOptions(?Iterable $config = null): AppInterface
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
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Search.
     *
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     *
     * @param array $query
     * @param int   $deleted
     *
     * @return array
     */
    public function search(array $query, int $deleted = NodeInterface::DELETED_INCLUDE): array
    {
        //if ($user instanceof User) {
        //    $user->findNewShares();
        //}

        $list = [];
        $id = false;

        $user = $this->server->getIdentity();
        $shares = $user->getShares(true);
        $result = $this->executeQuery($query, $shares);

        if (isset($result['error'])) {
            throw new Exception('failed search index, query failed');
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

        $user = (string) $user->getId();
        foreach ($result['hits']['hits'] as $node) {
            $id = false;

            if ('storage' === $node['_type']) {
                $id = $node['_id'];

                try {
                    $_node = $this->fs->findNodeWithId($id);
                    if ($_node->isDeleted() && (1 === $deleted || 2 === $deleted)
                     || !$_node->isDeleted() && (0 === $deleted || 2 === $deleted)) {
                        if (!($_node->isShare() && !$_node->isOwnerRequest())) {
                            $list[$id] = $_node;
                        }
                    }
                } catch (\Exception $e) {
                }
            } elseif ('fs' === $node['_type']) {
                foreach ($node['_source']['metadata']['ref'] as $n) {
                    if ($n['owner'] === $user) {
                        if (!array_key_exists($n['id'], $list)) {
                            try {
                                $_node = $this->fs->findNodeWithId($n['id']);
                                if ($_node->isDeleted() && (1 === $deleted || 2 === $deleted)
                                 || !$_node->isDeleted() && (0 === $deleted || 2 === $deleted)) {
                                    $list[$n['id']] = $_node;
                                }
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }

                foreach ($node['_source']['metadata']['share_ref'] as $n) {
                    if (in_array($n['share'], $shares, true)) {
                        if (!array_key_exists($n['id'], $list)) {
                            try {
                                $_node = $this->fs->findNodeWithId($n['id']);
                                if ($_node->isDeleted() && (1 === $deleted || 2 === $deleted)
                                || !$_node->isDeleted() && (0 === $deleted || 2 === $deleted)) {
                                    $list[$n['id']] = $_node;
                                }
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Search.
     *
     * @param array $query
     * @param array $share
     *
     * @return array
    */
    protected function executeQuery(array $query, array $shares): array
    {
        $user = $this->server->getIdentity();
        $bool = $query['body']['query'];

        $filter1 = [];
        $filter1['bool']['should'][]['term']['owner'] = (string) $user->getId();
        $filter1['bool']['should'][]['term']['metadata.ref.owner'] = (string) $user->getId();

        $share_filter = [
            'bool' => [
                'should' => [],
            ],
        ];

        foreach ($shares as $share) {
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

        $this->logger->debug('prepared elasticsearch query', [
            'category' => get_class($this),
            'params' => $query,
        ]);

        $result = $this->getEsClient()->search($query);

        if (null === $result) {
            $this->logger->error('failed search elastic, query returned NULL', [
                'category' => get_class($this),
            ]);

            throw new Exception('general search error occured');
        }

        return $result;
    }
}
