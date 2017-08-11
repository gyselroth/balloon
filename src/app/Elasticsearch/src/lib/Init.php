<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use \Balloon\User;
use \Balloon\Filesystem;
use \Balloon\Http\Router\Route;
use \Balloon\App\AbstractApp;

class Init extends AbstractApp
{
    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        return true;
    }


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        return true;
    }


    /**
     * Search
     *
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     *
     * @param   array $query
     * @param   int $deleted
     * @return  array
     */
    public function search(array $query, int $deleted=NodeInterface::DELETED_INCLUDE): array
    {
        #if ($this->user instanceof User) {
        #    $this->user->findNewShares();
        #}

        $list = [];
        $id = false;

        $shares = $this->user->getShares(true);
        $result = $this->_searchElastic($query, $shares);

        if (isset($result['error'])) {
            throw new Exception('failed search index, query failed');
        }
       
        $this->logger->debug('elasticsearch query executed with ['.$result['hits']['total'].'] hits', [
            'category' => get_class($this),
            'params'   => [
                'took'      => $result['took'],
                'timed_out' => $result['timed_out'],
                '_shards'   => $result['_shards'],
                'max_score' => $result['hits']['max_score'],
                'hits'      => $result['hits']['total'],
            ]
        ]);
        
        $user = (string)$this->user->getId();
        foreach ($result['hits']['hits'] as $node) {
            $id = false;

            if ($node['_type'] == 'storage') {
                $id = $node['_id'];
                    
                try {
                    $_node = $this->fs->findNodeWithId($id);
                    if ($_node->isDeleted() && ($deleted == 1 || $deleted == 2)
                     || !$_node->isDeleted() && ($deleted == 0 || $deleted == 2)) {
                        if (!($_node->isShare() && !$_node->isOwnerRequest())) {
                            $list[$id] = $_node;
                        }
                    }
                } catch (\Exception $e) {
                }
            } elseif ($node['_type'] == 'fs') {
                foreach ($node['_source']['metadata']['ref'] as $n) {
                    if ($n['owner'] === $user) {
                        if (!array_key_exists($n['id'], $list)) {
                            try {
                                $_node = $this->fs->findNodeWithId($n['id']);
                                if ($_node->isDeleted() && ($deleted == 1 || $deleted == 2)
                                 || !$_node->isDeleted() && ($deleted == 0 || $deleted == 2)) {
                                    $list[$n['id']] = $_node;
                                }
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }

                foreach ($node['_source']['metadata']['share_ref'] as $n) {
                    if (in_array($n['share'], $shares)) {
                        if (!array_key_exists($n['id'], $list)) {
                            try {
                                $_node = $this->fs->findNodeWithId($n['id']);
                                if ($_node->isDeleted() && ($deleted == 1 || $deleted == 2)
                                || !$_node->isDeleted() && ($deleted == 0 || $deleted == 2)) {
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
     * Search
     *
     * @param   array $query
     * @param   array $share
     * @return  array
     */
    protected function _searchElastic(array $query, array $shares): array
    {
        $client = \Elasticsearch\ClientBuilder::create()
                   ->setHosts((array)$this->config->search->hosts->server)
                   ->build();

        $bool =  $query['body']['query'];

        $filter1 = [];
        $filter1['bool']['should'][]['term']['owner'] = (string)$this->user->getId();
        $filter1['bool']['should'][]['term']['metadata.ref.owner'] = (string)$this->user->getId();

        $share_filter = [
            'bool' => [
                'should' => []
            ]
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
                $rights_filter
            ]
        ];

        $query['_source'] = ['metadata.*', '_id', 'owner'];
        $query['index']   = $this->config->search->index;

        $this->logger->debug('prepared elasticsearch query', [
            'category' => get_class($this),
            'params'   => $query,
        ]);
        
        $result = $client->search($query);
        if ($result === null) {
            $this->logger->error('failed search elastic, query returned NULL', [
                'category' => get_class($this),
            ]);

            throw new Exception('general search error occured');
        }

        return $result;
    }

}
