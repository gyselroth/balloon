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
use Balloon\Group\Factory as GroupFactory;
use Balloon\App\CoreApiv3\v3\Models\GroupFactory as GroupModelFactory;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use MongoDB\BSON\ObjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\User\Factory as UserFactory;
use Balloon\App\CoreApiv3\v3\Models\UserFactory as UserModelFactory;

class Groups
{
    /**
     * Group factory.
     *
     * @var GroupFactory
     */
    protected $group_factory;

    /**
     * Init.
     */
    public function __construct(GroupFactory $group_factory, GroupModelFactory $group_model_factory, UserFactory $user_factory, UserModelFactory $user_model_factory, Acl $acl)
    {
        $this->group_factory = $group_factory;
        $this->group_model_factory = $group_model_factory;
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
            $cursor = $this->group_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->group_model_factory);
        }

        $groups = $this->group_factory->getAll($query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $groups, $this->group_model_factory);
    }

    /**
     * Get member
     */
    public function getMembers(ServerRequestInterface $request, User $identity, ObjectId $group): ResponseInterface
    {
        $query = $request->getQueryParams();
        $resource = $this->group_factory->getOne($group);
        $members = $resource->getMembers();

        $filter = [
            '_id' => ['$in' => $members]
        ];

        if(count($query['query']) > 0) {
            $filter = [
                '$and' => [$filter,$query['query']],
            ];
        }

        if (isset($query['watch'])) {
            $cursor = $this->user_factory->watch(null, true, $filter, (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->user_model_factory);
        }
        $users = $this->user_factory->getAll($filter, $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $users, $this->user_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $group): ResponseInterface
    {
        $resource = $this->group_factory->getOne($group);

        return Helper::getOne($request, $identity, $resource, $this->group_model_factory);
    }

    /**
     * Delete group.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $group): ResponseInterface
    {
        $this->group_factory->deleteOne($group);
        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new group.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        $resource = $this->group_factory->add($body);
        return Helper::getOne($request, $identity, $resource, $this->group_model_factory, StatusCodeInterface::STATUS_CREATED);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $group): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $group = $this->group_factory->getOne($group);
        $update = Helper::patch($request, $group);
        $this->group_factory->update($group, $update);
        return Helper::getOne($request, $identity, $group, $this->group_model_factory);
    }
}
