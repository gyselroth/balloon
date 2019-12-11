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
use Balloon\Group\Factory as GroupFactory;
use Balloon\App\CoreApiv3\v3\Models\GroupFactory as GroupModelFactory;

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
    public function __construct(UserFactory $user_factory, UserModelFactory $user_model_factory, GroupFactory $group_factory, GroupModelFactory $group_model_factory, Acl $acl)
    {
        $this->user_factory = $user_factory;
        $this->user_model_factory = $user_model_factory;
        $this->group_factory = $group_factory;
        $this->group_model_factory = $group_model_factory;
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
     * Get groups
     */
    public function getGroups(ServerRequestInterface $request, User $identity, ObjectId $user): ResponseInterface
    {
        $query = $request->getQueryParams();
        $resource = $this->user_factory->getOne($user);
        $groups = $resource->getGroups();

        $filter = [
            '_id' => ['$in' => $groups]
        ];

        if(count($query['query']) > 0) {
            $filter = [
                '$and' => [$filter,$query['query']],
            ];
        }

        if (isset($query['watch'])) {
            $cursor = $this->groups_factory->watch(null, true, $filter, (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->group_model_factory);
        }

        $groups = $this->group_factory->getAll($filter, $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $groups, $this->group_model_factory);
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

        $resource = $this->user_factory->add($body);
        return Helper::getOne($request, $identity, $resource, $this->user_model_factory, StatusCodeInterface::STATUS_CREATED);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $user): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $user = $this->user_factory->getOne($user);
        $update = Helper::patch($request, $user);
        $this->user_factory->update($user, $update);
        return Helper::getOne($request, $identity, $user, $this->user_model_factory);
    }
}
