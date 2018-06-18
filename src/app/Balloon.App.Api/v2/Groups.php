<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\AttributeDecorator\Pager;
use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\Group;
use Balloon\Server\User;
use Balloon\Server\User\Exception;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Groups
{
    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
     *
     * @param AttributeDecorator
     */
    public function __construct(Server $server, AttributeDecorator $decorator)
    {
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->decorator = $decorator;
    }

    /**
     * @apiDefine _getGroup
     *
     * @apiParam (GET Parameter) {string[]} id Either a single id (group id) or a name (groupname) must be given (admin privilege required).
     * @apiParam (GET Parameter) {string[]} name Either a single id (group id) or a name (groupname) must be given (admin privilege required).
     *
     * @apiErrorExample {json} Error-Response (No admin privileges):
     * HTTP/1.1 403 Forbidden
     * {
     *      "status": 403,
     *      "data": {
     *          "error": "Balloon\\Exception\\Forbidden",
     *          "message": "submitted parameters require to have admin privileges",
     *          "code": 41
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Group not found):
     * HTTP/1.1 404 Not Found
     * {
     *      "status": 404,
     *      "data": {
     *          "error": "Balloon\\Exception\\NotFound",
     *          "message": "requested group was not found",
     *          "code": 53
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Invalid argument):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\InvalidArgument",
     *          "message": "provide either id (group id) or name (groupname)",
     *          "Code": 0
     *      }
     * }
     */

    /**
     * @apiDefine _getGroups
     *
     * @apiParam (GET Parameter) {string[]} id Either a single id (group id) as string or multiple as an array or a single name (groupname) as string or multiple groupnames as array must be given.
     * @apiParam (GET Parameter) {string[]} name Either a single id (groupid) as string or multiple as an array or a single name (groupname) as string or multiple groupnames as array must be given.
     */

    /**
     * Get group instance.
     */
    public function _getGroup(string $id, bool $require_admin = false): Group
    {
        if (true === $require_admin && !$this->user->isAdmin()) {
            throw new Exception\NotAdmin('submitted parameters require admin privileges');
        }

        return $this->server->getGroupById(new ObjectId($id));
    }

    /**
     * @api {get} /api/v2/groups/:id/members Get group member
     * @apiVersion 2.0.0
     * @apiName getMember
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission none
     * @apiDescription Request all member of a group
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/groups/member?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/member?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/member?name=logingroup&pretty"
     *
     * @apiSuccess {object[]} - List of user
     * @apiSuccess {string} -.id User ID
     * @apiSuccess {string} -.name Username
     * @apiSuccess {string} -.mail Mail address
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "id": "544627ed3c58891f058b4613",
     *      "name": "ted",
     *      "mail": "test@example.org"
     *  }
     * ]
     */
    public function getMembers(string $id, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        $group = $this->_getGroup($id);
        $result = $group->getResolvedMembers($offset, $limit);
        $uri = '/api/v2/groups/'.$group->getId().'/members';
        $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, $uri);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/groups/:id Get group attributes
     * @apiVersion 2.0.0
     * @apiName get
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission none
     * @apiDescription Get group attributes
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/groups/attributes?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/attributes?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/attributes?name=loginser&pretty"
     *
     * @apiSuccess (200 OK) {string} id group ID
     * @apiSuccess (200 OK) {string} name group name
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b46cd",
     *      "name": "test"
     * }
     *
     * @param string $id
     * @param string $attributes
     */
    public function get(?string $id = null, array $query = [], array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if ($id === null) {
            $result = $this->server->getGroups($query, $offset, $limit);
            $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, '/api/v2/groups');
            $result = $pager->paging();
        } else {
            $result = $this->decorator->decorate($this->_getGroup($id), $attributes);
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {head} /api/v2/groups/:id Group exists
     * @apiVersion 2.0.0
     * @apiName postQuota
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Check if group account exists
     *
     * @apiExample Example usage:
     * curl -XHEAD "https://SERVER/api/v2/group"
     * curl -XHEAD "https://SERVER/api/v2/groups/544627ed3c58891f058b4611"
     * curl -XHEAD "https://SERVER/api/v2/group?name=logingroup"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function head(string $id): Response
    {
        $result = $this->_getGroup($id, true);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/groups Create group
     * @apiVersion 2.0.0
     * @apiName postGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Create group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/group"
     *
     * @apiParam (POST Parameter) {string} name group name
     * @apiParam (POST Parameter) {string[]} member Array of member id
     * @apiParam (POST Parameter) {string} namespace Namespace
     * @apiParam (POST Parameter) {string[]} optional Optional attributes
     *
     * @apiSuccess (200 OK) {string} id group ID
     * @apiSuccess (200 OK) {string} name group name
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "id": "544627ed3c58891f058b46cd",
     *      "name": "test"
     * }
     *
     * @param array $member
     */
    public function post(string $name, ?array $member = null, ?string $namespace = null, ?array $optional = null): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Exception\NotAdmin('submitted parameters require admin privileges');
        }

        $attributes = compact('namespace', 'optional');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $id = $this->server->addGroup($name, $member, $attributes);
        $result = $this->decorator->decorate($this->server->getGroupById($id));

        return (new Response())->setBody($result)->setCode(201);
    }

    /**
     * @api {patch} /api/v2/groups/:id Change group attributes
     * @apiVersion 2.0.0
     * @apiName patch
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Set attributes for group
     *
     * @apiParam (POST Parameter) {string} name group name
     * @apiParam (POST Parameter) {string[]} member Array of member id
     * @apiParam (POST Parameter) {string} namespace Namespace
     * @apiParam (POST Parameter) {string[]} optional Optional attributes
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/groups/attributes" -d '{"attributes": ["mail": "group@example.com"]}'
     * curl -XPOST "https://SERVER/api/v2/groups/attributes?{%22attributes%22:[%22mail%22:%22group@example.com%22]}""
     * curl -XPOST "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/attributes" -d '{"attributes": ["admin": "false"]}'
     * curl -XPOST "https://SERVER/api/v2/groups/quota?name=logingroup"  -d '{"attributes": ["admin": "false"]}'
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     */
    public function patch(string $id, ?array $member = null, ?string $namespace = null, ?array $optional = null): Response
    {
        $attributes = compact('namespace', 'optional', 'name', 'member');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $group = $this->_getGroup($id, true);
        $group->setAttributes($attributes);
        $result = $this->decorator->decorate($group);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {delete} /api/v2/groups/:id Delete group
     * @apiVersion 2.0.0
     * @apiName delete
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Delete group
     *
     * @apiExample Example usage:
     * curl -XDELETE "https://SERVER/api/v2/groups/544627ed3c58891f058b4611?force=1"
     * curl -XDELETE "https://SERVER/api/v2/group?name=logingroup"
     *
     * @apiParam (GET Parameter) {bool} [force=false] Per default the group gets disabled, if force is set
     * the group gets removed completely.
     *
     * @apiErrorExample {json} Error-Response (Can not delete yourself):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "requested group was not found"
     *      }
     * }
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function delete(string $id, bool $force = false): Response
    {
        $group = $this->_getGroup($id, true);
        $group->delete($force);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/groups/:id/undelete Restore group
     * @apiVersion 2.0.0
     * @apiName postUndelete
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Restore deleted group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/undelete"
     * curl -XPOST "https://SERVER/api/v2/groups/undelete?group=logingroup"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function postUndelete(string $id): Response
    {
        $this->_getGroup($id, true)->undelete();

        return (new Response())->setCode(204);
    }
}
