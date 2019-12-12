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
use Balloon\AccessRole;
use Balloon\AccessRole\Factory as AccessRoleFactory;
use Balloon\App\CoreApiv3\v3\Models\AccessRoleFactory as AccessRoleModelFactory;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use MongoDB\BSON\ObjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\Group\Factory as GroupFactory;
use Balloon\App\CoreApiv3\v3\Models\GroupFactory as GroupModelFactory;

class AccessRoles
{
    /**
     * AccessRole factory.
     *
     * @var AccessRoleFactory
     */
    protected $access_role_factory;

    /**
     * Init.
     */
    public function __construct(AccessRoleFactory $access_role_factory, AccessRoleModelFactory $access_role_model_factory, Acl $acl)
    {
        $this->access_role_factory = $access_role_factory;
        $this->access_role_model_factory = $access_role_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->access_role_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->access_role_model_factory);
        }

        $access_roles = $this->access_role_factory->getAll($query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $access_roles, $this->access_role_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $access_role): ResponseInterface
    {
        $resource = $this->access_role_factory->getOne($access_role);

        return Helper::getOne($request, $identity, $resource, $this->access_role_model_factory);
    }

    /**
     * Delete access_role.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $access_role): ResponseInterface
    {
        $this->access_role_factory->deleteOne($access_role);
        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new access_role.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $resource = $this->access_role_factory->add($body);
        return Helper::getOne($request, $identity, $resource, $this->access_role_model_factory, StatusCodeInterface::STATUS_CREATED);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $access_role): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $access_role = $this->access_role_factory->getOne($access_role);
        $update = Helper::patch($request, $access_role);
        $this->access_role_factory->update($access_role, $update);
        return Helper::getOne($request, $identity, $access_role, $this->access_role_model_factory);
    }
}
