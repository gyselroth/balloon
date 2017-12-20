<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Api\Latest;

use Balloon\App\Api\Controller;
use Balloon\App\Elasticsearch\Elasticsearch;
use Balloon\Exception;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Helper;
use Micro\Http\Response;
use Psr\Log\LoggerInterface;

class Search extends Controller
{
    /**
     * Elasticsearch.
     *
     * @var Elasticsearch
     */
    protected $es;

    /**
     * Attribut decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param Elasticsearch      $es
     * @param AttributeDecorator $decorator
     * @param LoggerInterface    $logger
     */
    public function __construct(Elasticsearch $es, AttributeDecorator $decorator, LoggerInterface $logger)
    {
        $this->es = $es;
        $this->decorator = $decorator;
        $this->logger = $logger;
    }

    /**
     * @api {get} /api/v2/node/search Search
     * @apiVersion 1.0.0
     * @apiName getSearch
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Extended search query using elasticsearch
     * @apiUse _nodeAttributes
     *
     * @apiExample (cURL) example:
     * #Fulltext search and search for a name
     * curl -XGET -H 'Content-Type: application/json' "https://SERVER/api/v2/node/search?pretty" -d '{
     *           "body": {
     *               "query": {
     *                   "bool": {
     *                       "should": [
     *                           {
     *                               "match": {
     *                                   "content": "house"
     *                               }
     *                           },
     *                           {
     *                               "match": {
     *                                   "name": "file.txt"
     *                               }
     *                           }
     *                       ]
     *                   }
     *               }
     *           }
     *       }'
     *
     * @apiParam (GET Parameter) {object} query Elasticsearch query object
     * @apiParam (GET Parameter) {string[]} [attributes] Filter node attributes
     * @apiParam (GET Parameter) {number} [deleted=0] Wherever include deleted nodes or not, possible values:</br>
     * - 0 Exclude deleted</br>
     * - 1 Only deleted</br>
     * - 2 Include deleted</br>
     *
     * @apiSuccess (200 OK) {object[]} data Node list (matched nodes)
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data": [{...}, {...}]
     *      }
     * }
     *
     * @param array $query
     * @param array $attributes
     * @param int   $deleted
     *
     * @return Response
     */
    public function get(array $query, array $attributes = [], int $deleted = 0): Response
    {
        $children = [];
        $nodes = $this->es->search($query, $deleted);

        foreach ($nodes as $node) {
            try {
                $child = Helper::escape($this->decorator->decorate($node, $attributes));
                $children[] = $child;
            } catch (\Exception $e) {
                $this->logger->info('error occured during loading attributes, skip search result node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return (new Response())->setCode(200)->setBody($children);
    }
}