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
use Balloon\Rest\Helper;
use Balloon\User;
use Balloon\User\Factory as UserFactory;
use Balloon\App\CoreApiv3\v3\Models\UserFactory as UserModelFactory;
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
    public function __construct(UserFactory $user_factory, UserModelFactory $user_model_factory, Acl $acl)
    {
        $this->user_factory = $user_factory;
        $this->user_model_factory = $user_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->user_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->user_model_factory);
        }

        $users = $this->user_factory->getAll($query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $users, $this->user_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $user): ResponseInterface
    {
        $resource = $this->user_factory->getOne($user);

        return Helper::getOne($request, $identity, $resource, $this->user_model_factory);
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
            $this->user_model_factory->decorate($resource, $request),
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

        return Helper::getOne($request, $identity, $update, $this->user_model_factory);
    }
}
