<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\Latest\Resource as LatestResource;
use Micro\Http\Response;
use MongoDB\BSON\Regex;

class Resource extends LatestResource
{
    /**
     * @api {get} /api/v1/resource/acl-roles?q=:query&namespace=:namespace Query available acl roles
     * @apiVersion 1.0.0
     * @apiName getAclRoles
     * @apiGroup Resource
     * @apiPermission none
     * @apiDescription Query available acl roles (user and groups)
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/acl-roles?q=peter&namespace=organization&pretty"
     *
     * @apiParam (GET Parameter) {string} [1] Search query (user/group)
     * @apiParam (GET Parameter) {boolean} [single] Search request for a single user (Don't have to be in namespace)
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object[]} roles All roles found with query search string
     * @apiSuccess {string} roles.type ACL role type (user|group)
     * @apiSuccess {string} roles.id Role identifier (Could be the same as roles.name)
     * @apiSuccess {string} roles.name Role name (human readable name)
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": [
     *          {
     *              "type": "user",
     *              "id": "peter.meier",
     *              "name": "peter.meier"
     *          },
     *          {
     *              "type": "group",
     *              "id": "peters",
     *              "name": "peters"
     *          }
     *      ]
     * }
     *
     * @param string $q
     * @param bool   $single
     *
     * @return Response
     */
    public function getAclRoles(string $q, bool $single = false, array $attributes = []): Response
    {
        if (true === $single) {
            $regex = new Regex('^'.preg_quote($q).'$', 'i');
            $users_filter = [
                'username' => $regex,
            ];
            $groups_filter = [
                'name' => $regex,
            ];
        } else {
            $regex = new Regex('^'.preg_quote($q), 'i');
            $users_filter = [
                'username' => $regex,
                'namespace' => $this->user->getNamespace(),
            ];
            $groups_filter = [
                'name' => $regex,
                'namespace' => $this->user->getNamespace(),
            ];
        }

        $body = [];

        foreach ($this->server->getGroups($groups_filter) as $role) {
            $body[] = [
                'type' => 'group',
                'id' => (string) $role->getId(),
                'name' => (string) $role->getName(),
            ];
        }

        foreach ($this->server->getUsers($users_filter) as $role) {
            $body[] = [
                'type' => 'user',
                'id' => (string) $role->getId(),
                'name' => (string) $role->getUsername(),
            ];
        }

        return (new Response())->setCode(200)->setBody($body);
    }
}
