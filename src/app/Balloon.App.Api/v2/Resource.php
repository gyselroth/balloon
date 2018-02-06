<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\User;
use Micro\Http\Response;
use MongoDB\BSON\Regex;

class Resource
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
     *
     * @param Server             $server
     * @param AttributeDecorator $decorator
     */
    public function __construct(Server $server, AttributeDecorator $decorator)
    {
        $this->server = $server;
        $this->user = $server->getIdentity();
        $this->decorator = $decorator;
    }

    /**
     * @api {get} /resource/acl-roles?q=:query Query available acl roles
     * @apiVersion 2.0.0
     * @apiName getAclRoles
     * @apiGroup Resource
     * @apiPermission none
     * @apiDescription Query available acl roles (user and groups)
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/user/acl-roles?q=peter"
     *
     * @apiParam (GET Parameter) {string} [q] Search query (user/group)
     * @apiParam (GET Parameter) {boolean} [single] Search request for a single user (Don't have to be in namespace)
     * @apiParam (GET Parameter) {array} [attributes] Specify user/group attributes
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object[]} data All roles found with query search string
     * @apiSuccess {string} data.type ACL role type (user|group)
     * @apiSuccess {object} data.role Role attributes (user/group)
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": [
     *          {
     *              "type": "user",
     *              "role": {,
     *                  "id": "5a2e6e43db830e003c3eb3b2",
     *                  "username": "peter.example"
     *              }
     *          },
     *          {
     *              "type": "group",
     *              "role": {
     *                  "id": "5a2e6e43db830e003c3eb3ca",
     *                  "name": "testgroup"
     *              }
     *          }
     *      ]
     * }
     *
     * @param string $q
     * @param bool   $single
     * @param array  $attributes
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
                'role' => $this->decorator->decorate($role, $attributes),
            ];
        }

        foreach ($this->server->getUsers($users_filter) as $role) {
            $body[] = [
                'type' => 'user',
                'role' => $this->decorator->decorate($role, $attributes),
            ];
        }

        return (new Response())->setCode(200)->setBody($body);
    }
}
