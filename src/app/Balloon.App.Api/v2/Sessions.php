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
use Balloon\Collection\Factory as CollectionFactory;
use Balloon\Session\Factory as SessionFactory;
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\App\Api\v2\Models\SessionFactory as SessionModelFactory;
use MongoDB\BSON\ObjectId;

class Sessions
{
    /**
     * Node factory.
     *
     * @var CollectionFactory
     */
    protected $session_factory;

    /**
     * Init.
     */
    public function __construct(SessionFactory $session_factory, CollectionFactory $collection_factory, SessionModelFactory $session_model_factory, Acl $acl)
    {
        $this->session_factory = $session_factory;
        $this->collection_factory = $collection_factory;
        $this->session_model_factory = $session_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->session_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor);
        }

        $sessions = $this->session_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);
        return Helper::getAll($request, $identity, $this->acl, $sessions, $this->session_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $session): ResponseInterface
    {
        $resource = $this->session_factory->getOne($identity, $session);
        return Helper::getOne($request, $identity, $resource, $this->session_model_factory);
    }

    /**
     * Delete node.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $session): ResponseInterface
    {
        $this->session_factory->deleteOne($identity, $session);
        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new node.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getBody()->detach();
        $query = $request->getQueryParams();

        $parent = $this->collection_factory->getOne($identity, $query['parent'] ?? null);
        $resource = $this->session_factory->add($identity, $parent, $body);
        return Helper::getOne($request, $identity, $resource, $this->session_model_factory);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $session): ResponseInterface
    {
        $session = $this->session_factory->getOne($identity, $node);
        $body = $request->getBody()->detach();
        $query = $request->getQueryParams();
        $parent = $this->collection_factory->getOne($identity, $query['parent'] ?? null);
        $result = $this->session_factory->update($identity, $session, $parent, $body);
        return Helper::getOne($request, $identity, $session, $this->session_model_factory);
    }
}
