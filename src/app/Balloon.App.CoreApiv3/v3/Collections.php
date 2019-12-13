<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3\v3;

use Balloon\Acl;
use Balloon\Node;
use Balloon\Node\Factory as NodeFactory;
use Balloon\Collection\Factory as CollectionFactory;
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use MongoDB\BSON\ObjectId;
use Balloon\App\CoreApiv3\v3\Models\NodeFactory as NodeModelFactory;

class Collections
{
    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $collection_factory;

    /**
     * Init.
     */
    public function __construct(CollectionFactory $collection_factory, NodeModelFactory $node_model_factory, Acl $acl)
    {
        $this->collection_factory = $collection_factory;
        $this->node_model_factory = $node_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->collection_factory->watch($identity, null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->node_model_factory);
        }

        $collections = $this->collection_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $collections, $this->node_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getChildren(ServerRequestInterface $request, User $identity, ?ObjectId $collection=null): ResponseInterface
    {
        $query = $request->getQueryParams();
        $resource = $this->collection_factory->getOne($identity, $collection);

        if (isset($query['watch'])) {
            $cursor = $this->collection_factory->watchChildren($identity, null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->node_model_factory);
        }

        $recursive = isset($query['recursive']) && $query['recursive'] !== 'false' ? (bool)$query['recursive'] : false;
        $collections = $this->collection_factory->getChildren($identity, $resource, $query['query'], $query['offset'], $query['limit'], $query['sort'], $recursive);
        return Helper::getAll($request, $identity, $this->acl, $collections, $this->node_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $collection): ResponseInterface
    {
        $resource = $this->collection_factory->getOne($identity, $collection);

        return Helper::getOne($request, $identity, $resource, $this->node_model_factory);
    }
}
