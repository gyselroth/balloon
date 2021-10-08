<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Api\v2;

use Balloon\App\Elasticsearch\Elasticsearch;
use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem\Node\AttributeDecorator;
use Micro\Http\Response;
use Psr\Log\LoggerInterface;

class Search
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
     * Search.
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
