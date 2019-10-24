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
use Balloon\Rest\Helper;
use Balloon\User;
use Balloon\User\Factory as UserFactory;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use MongoDB\BSON\ObjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;

class Users
{
    /**
     * User factory.
     *
     * @var UserFactory
     */
    protected $user_factory;

    /**
     * Init.
     */
    public function __construct(UserFactory $user_factory, Acl $acl)
    {
        $this->user_factory = $user_factory;
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
            $cursor = $this->user_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor);
        }

        $users = $this->user_factory->getAll($query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $users);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $user): ResponseInterface
    {
        $resource = $this->user_factory->getOne($user);

        return Helper::getOne($request, $identity, $resource);
    }

    /**
     * Delete user.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $user): ResponseInterface
    {
        $this->user_factory->deleteOne($user);

        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new user.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        $id = $this->user_factory->add($body);

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_CREATED),
            $this->user_factory->getOne($id)->decorate($request),
            ['pretty' => isset($query['pretty'])]
        );
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $user): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $user = $this->user_factory->getOne($user);
        $doc = ['data' => $user->getData()];

        $patch = new Patch(json_encode($doc), json_encode($body));
        $patched = $patch->apply();
        $update = json_decode($patched, true);
        $this->user_factory->update($user, $update);

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_OK),
            $this->user_factory->getOne($user->getName())->decorate($request),
            ['pretty' => isset($query['pretty'])]
        );
    }
}
