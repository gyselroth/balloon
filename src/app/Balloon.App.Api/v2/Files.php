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
use Balloon\File\Factory as FileFactory;
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\App\Api\v2\Models\NodeFactory as NodeModelFactory;

class Files
{
    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $file_factory;

    /**
     * Init.
     */
    public function __construct(FileFactory $file_factory, NodeModelFactory $node_model_factory, Acl $acl)
    {
        $this->file_factory = $file_factory;
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
            $cursor = $this->file_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor);
        }

        $files = $this->file_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $files, $this->node_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $file): ResponseInterface
    {
        $resource = $this->file_factory->getOne($identity, $file);
        return Helper::getOne($request, $identity, $resource, $this->node_model_factory);
    }

    /**
     * Delete node.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $file): ResponseInterface
    {
        $this->file_factory->deleteOne($identity, $file);

        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new node.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        $resource = $this->file_factory->add($identity, $body);
        return Helper::getOne($request, $identity, $resource, $this->node_model_factory);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $file): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $file = $this->file_factory->getOne($identity, $file);

        $patch = new Patch(json_encode($file->toArray()), json_encode($body));
        $patched = $patch->apply();
        $update = json_decode($patched, true);
        $this->file_factory->update($file, $update);
        return Helper::getOne($request, $identity, $resource, $this->node_model_factory);
    }
}
