<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

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
use Balloon\App\Api\v2\Models\NodeFactory as NodeModelFactory;

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
    public function getAll(ServerRequestInterface $request, User $user): ResponseInterface
    {
        $query = array_merge([
            'offset' => 0,
            'limit' => 20,
        ], $request->getQueryParams());

        if (isset($query['watch'])) {
            $cursor = $this->collection_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $user, $this->acl, $cursor);
        }

        $collections = $this->collection_factory->getAll($user, $query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $user, $this->acl, $collections, $this->node_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getChildren(ServerRequestInterface $request, User $user, ObjectId $collection): ResponseInterface
    {
        $query = $request->getQueryParams();
        $resource = $this->collection_factory->getOne($user, $collection);

        if (isset($query['watch'])) {
            $cursor = $this->collection_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $user, $this->acl, $cursor);
        }

        $collections = $this->collection_factory->getChildren($user, $resource, $query['query'], $query['offset'], $query['limit'], $query['sort'], (bool)$query['recursive']);
        return Helper::getAll($request, $user, $this->acl, $collections, $this->node_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $user, ObjectId $collection): ResponseInterface
    {
        $resource = $this->collection_factory->getOne($user, $collection);

        return Helper::getOne($request, $user, $resource, $this->node_model_factory);
    }

    /**
     * Delete node.
     */
    public function delete(ServerRequestInterface $request, User $user, ObjectId $collection): ResponseInterface
    {
        $this->collection_factory->deleteOne($user, $collection);

        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new node.
     */
    public function post(ServerRequestInterface $request, User $user): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        $resource = $this->collection_factory->add($user, $body);
        return Helper::getOne($request, $user, $resource, $this->node_model_factory);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $user, ObjectId $collection): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $collection = $this->collection_factory->getOne($user, $collection);

        $patch = new Patch(json_encode($collection->toArray()), json_encode($body));
        $patched = $patch->apply();
        $update = json_decode($patched, true);
        $this->collection_factory->update($collection, $update);
        return Helper::getOne($request, $user, $resource, $this->node_model_factory);
    }
}
