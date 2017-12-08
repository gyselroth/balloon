<?php

declare(strict_types = 1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api\v1;

use Balloon\Exception;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Group
{
    /**
     * Group.
     *
     * @var Group
     */
    protected $group;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Initialize.
     *
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->group = $server->getIdentity();
        $this->server = $server;
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
     *
     * @param string $id
     * @param string $name
     * @param bool   $require_admin
     *
     * @return Group
     */
    public function _getGroup(?string $id = null, ?string $name = null, bool $require_admin = false)
    {
        if (null !== $id || null !== $name || true === $require_admin) {
            if ($this->group->isAdmin()) {
                if (null !== $id && null !== $name) {
                    throw new Exception\InvalidArgument('provide either id (group id) or name (groupname)');
                }

                if (null !== $id) {
                    return $this->server->getGroupById(new ObjectId($id));
                }

                return $this->server->getGroupByName($name);
            }

            throw new ForbiddenException(
                    'submitted parameters require to have admin privileges',
                    ForbiddenException::ADMIN_PRIV_REQUIRED
                );
        }

        return $this->group;
    }

    /**
     * @api {get} /api/v1/group/member Group member
     * @apiVersion 1.0.0
     * @apiName getGroups
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission none
     * @apiDescription Get all group groups
     * If you want to receive your own groups you have to leave the parameters id and name empty.
     * Requesting this api with parameter id or name requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/group/groups?pretty"
     * curl -XGET "https://SERVER/api/v1/group/544627ed3c58891f058b4611/groups?pretty"
     * curl -XGET "https://SERVER/api/v1/group/groups?name=logingroup&pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {string[]} data  All groups with membership
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": [
     *          "group1",
     *          "group2",
     *     ]
     * }
     *
     * @param string $id
     * @param string $name
     */
    public function getMember(?string $id = null, ?string $name = null) : Response
    {
        $result = $this->_getGroup($id, $name)->getGroups();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/group/attributes Group attributes
     * @apiVersion 1.0.0
     * @apiName getAttributes
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission none
     * @apiDescription Get all group attributes including groupname, mail, id,....
     * If you want to receive your own attributes you have to leave the parameters id and name empty.
     * Requesting this api with parameter id or name requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/group/attributes?pretty"
     * curl -XGET "https://SERVER/api/v1/group/544627ed3c58891f058b4611/attributes?pretty"
     * curl -XGET "https://SERVER/api/v1/group/attributes?name=loginser&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} group attributes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [] //shortened
     * }
     *
     * @param string $id
     * @param string $name
     * @param string $attributes
     *
     * @return Response
     */
    public function getAttributes(?string $id = null, ?string $name = null, array $attributes = []) : Response
    {
        $result = $this->_getGroup($id, $name)->getAttribute($attributes);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {head} /api/v1/group?id=:id Group exists?
     * @apiVersion 1.0.0
     * @apiName postQuota
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Check if group account exists
     *
     * @apiExample Example usage:
     * curl -XHEAD "https://SERVER/api/v1/group"
     * curl -XHEAD "https://SERVER/api/v1/group/544627ed3c58891f058b4611"
     * curl -XHEAD "https://SERVER/api/v1/group?name=logingroup"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $name
     * @param string $id
     *
     * @return Response
     */
    public function head(?string $id = null, ?string $name = null) : Response
    {
        $result = $this->_getGroup($id, $name, true);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v1/group
     * @apiVersion 1.0.0
     * @apiName postGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Create group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v1/group"
     *
     * @apiParam (POST Parameter) {string} name of the new group
     * @apiParam (POST Parameter) {string[]} ID of group member
     * @apiParam (POST Parameter) {string[]} Attributes
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} group attributes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "status": 201,
     *      "data": "544627ed3c58891f058b4633"
     * }
     *
     * @param string $name
     * @param array  $member
     * @param array  $attributes
     *
     * @return Response
     */
    public function post(string $name, array $member, array $attributes = []): Response
    {
        $id = $this->server->addGroup($name, $member, $attributes);

        return (new Response())->setBody((string)$id)->setCode(201);
    }

    /**
     * @api {post} /api/v1/group/attributes?id=:id Set attributes
     * @apiVersion 1.0.0
     * @apiName postAttributes
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Set attributes for group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v1/group/attributes" -d '{"attributes": ["mail": "group@example.com"]}'
     * curl -XPOST "https://SERVER/api/v1/group/attributes?{%22attributes%22:[%22mail%22:%22group@example.com%22]}""
     * curl -XPOST "https://SERVER/api/v1/group/544627ed3c58891f058b4611/attributes" -d '{"attributes": ["admin": "false"]}'
     * curl -XPOST "https://SERVER/api/v1/group/quota?name=logingroup"  -d '{"attributes": ["admin": "false"]}'
     *
     * @apiParam (POST Parameter) {number} hard The new hard quota in bytes
     * @apiParam (POST Parameter) {number} soft The new soft quota in bytes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $name
     * @param string $id
     * @param array  $attributes
     *
     * @return Response
     */
    public function postAttributes(array $attributes = [], ?string $id = null, ?string $name = null) : Response
    {
        $this->_getGroup($id, $name, true)->setAttribute($attributes)->save(array_keys($attributes));

        return (new Response())->setCode(204);
    }

    /**
     * @api {delete} /api/v1/group?id=:id Delete group
     * @apiVersion 1.0.0
     * @apiName delete
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Delete group account, this will also remove any data owned by the group. If force is false, all data gets moved to the trash. If force
     * is true all data owned by the group gets ereased.
     *
     * @apiExample Example usage:
     * curl -XDELETE "https://SERVER/api/v1/group/544627ed3c58891f058b4611?force=1"
     * curl -XDELETE "https://SERVER/api/v1/group?name=logingroup"
     *
     * @apiParam (GET Parameter) {bool} [force=false] Per default the group account will be disabled, if force is set
     * the group account gets removed completely.
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
     *
     * @param string $name
     * @param string $id
     *
     * @return Response
     */
    public function delete(?string $id = null, ?string $name = null) : Response
    {
        $group = $this->_getGroup($id, $name, true);
        $group->delete();

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v1/group/undelete?id=:id Reactivate group account
     * @apiVersion 1.0.0
     * @apiName postUndelete
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Apiore group account. This endpoint does not restore any data, it only does reactivate an existing group account.
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v1/group/544627ed3c58891f058b4611/undelete"
     * curl -XPOST "https://SERVER/api/v1/group/undelete?group=logingroup"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $name
     * @param string $id
     *
     * @return Response
     */
    public function postUndelete(?string $id = null, ?string $name = null) : Response
    {
        $this->_getGroup($id, $name)->undelete();

        return (new Response())->setCode(204);
    }
}
