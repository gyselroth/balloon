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
use Balloon\App\CoreApiv3\Helper as ApiHelper;
use Balloon\App\CoreApiv3\v3\Models\ProcessFactory as ProcessModelFactory;
use Balloon\App\CoreApiv3\v3\Models\NodeFactory as NodeModelFactory;
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use MongoDB\BSON\ObjectId;
use TaskScheduler\Scheduler;
use Balloon\Async;
use Balloon\Process\Factory as ProcessFactory;
use Balloon\Collection\CollectionInterface;

class Nodes
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
    public function __construct(NodeFactory $node_factory, NodeModelFactory $node_model_factory, ProcessFactory $process_factory, ProcessModelFactory $process_model_factory, Acl $acl)
    {
        $this->node_factory = $node_factory;
        $this->node_model_factory = $node_model_factory;
        $this->process_factory = $process_factory;
        $this->process_model_factory = $process_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->node_factory->watch($identity, null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor);
        }

        $nodes = $this->node_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $nodes, $this->node_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $node): ResponseInterface
    {
        $resource = $this->node_factory->getOne($identity, $node);

        return Helper::getOne($request, $identity, $resource, $this->node_model_factory);
    }

    /**
     * Stream content.
     */
    public function getContent(ServerRequestInterface $request, User $identity, ObjectId $node): ResponseInterface
    {
        $resource = $this->node_factory->getOne($identity, $node);
        if ($node instanceof CollectionInterface) {
            return $node->getZip();
        }

        return ApiHelper::streamContent($request, $resource);
    }

    /**
     * Delete node.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $node): ResponseInterface
    {
         $node = $this->node_factory->getOne($user, $node);
         $result = $this->scheduler->addJob(Async\DeleteNode::class, [
             'user' => $identity->getId(),
             'node' => $node->getId(),
         ]);

         $resource = $this->process_factory->build($result->toArray(), $identity);
        return Helper::getOne($request, $identity, $resource, $this->process_model_factory, StatusCodeInterface::STATUS_ACCEPTED);
    }

    /**
     * Add new node.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $resource = $this->node_factory->add($identity, $body);
        return Helper::getOne($request, $identity, $resource, $this->node_model_factory, StatusCodeInterface::STATUS_CREATED);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $node): ResponseInterface
    {
        $node = $this->node_factory->getOne($identity, $node);
        $update = Helper::patch($request, $node);
        $result = $this->node_factory->update($identity, $node, $update);

        if($result === null) {
            return Helper::getOne($request, $identity, $node, $this->node_model_factory);
        }

         $resource = $this->process_factory->build($result->toArray(), $identity);
        return Helper::getOne($request, $identity, $resource, $this->process_model_factory, StatusCodeInterface::STATUS_ACCEPTED);
    }
}
