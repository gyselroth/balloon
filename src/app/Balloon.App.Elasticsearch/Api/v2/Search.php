<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Elasticsearch\Elasticsearch;
use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem\Node\AttributeDecorator;
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
     */
    public function __construct(Elasticsearch $es, AttributeDecorator $decorator, LoggerInterface $logger)
    {
        $this->es = $es;
        $this->decorator = $decorator;
        $this->logger = $logger;
    }

    /**
     * @api {get} /api/v2/nodes/search Search
     * @apiVersion 2.0.0
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
     * @apiSuccess (200 OK) {object[]} - List of nodes
     * @apiSuccess (200 OK) {string} -.id Node ID
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "id": "5a745e57dbbb21002668a702"
     *  }
     * ]
     */
    public function get(array $query, array $attributes = [], int $deleted = 0, int $offset = 0, $limit = 20): Response
    {
        $children = [];
        $result = $this->es->search($query, $deleted, $offset, $limit, $total);
        $uri = '/api/v2/nodes/search';
        $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, $uri, $total);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }
}
