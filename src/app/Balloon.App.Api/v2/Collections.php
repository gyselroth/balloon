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
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;

class Collections
{
    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $node_factory;

    /**
     * Init.
     */
    public function __construct(NodeFactory $node_factory, Acl $acl)
    {
        $this->node_factory = $node_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = array_merge([
            'offset' => 0,
            'limit' => 20,
        ], $request->getQueryParams());

        if (isset($query['watch'])) {
            $cursor = $this->node_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor);
        }

        $nodes = $this->node_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $nodes);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, string $node): ResponseInterface
    {
        $resource = $this->node_factory->getOne($identity, $node);

        return Helper::getOne($request, $identity, $resource);
    }

    /**
     * Delete node.
     */
    public function delete(ServerRequestInterface $request, User $identity, string $node): ResponseInterface
    {
        $this->node_factory->deleteOne($identity, $node);

        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new node.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        $id = $this->node_factory->add($identity, $body);

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_CREATED),
            $this->node_factory->getOne($body['name'])->decorate($request),
            ['pretty' => isset($query['pretty'])]
        );
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, string $node): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $node = $this->node_factory->getOne($node);
        $doc = ['data' => $node->getData()];

        $patch = new Patch(json_encode($doc), json_encode($body));
        $patched = $patch->apply();
        $update = json_decode($patched, true);
        $this->node_factory->update($node, $update);

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_OK),
            $this->node_factory->getOne($node->getName())->decorate($request),
            ['pretty' => isset($query['pretty'])]
        );
    }
}
