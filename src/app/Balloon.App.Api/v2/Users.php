<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\Exception\InvalidArgument as InvalidArgumentException;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\User;
use Balloon\Server\User\Exception;
use Micro\Http\Response;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;

class Users
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
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
     *
     * @param Server $server
     */
    public function __construct(Server $server, AttributeDecorator $decorator)
    {
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->decorator = $decorator;
    }

    /**
     * @apiDefine _getUser
     *
     * @apiParam (GET Parameter) {string[]} uid Either a single uid (user id) or a uname (username) must be given (admin privilege required).
     * @apiParam (GET Parameter) {string[]} uname Either a single uid (user id) or a uname (username) must be given (admin privilege required).
     *
     * @apiErrorExample {json} Error-Response (No admin privileges):
     * HTTP/1.1 403 Forbidden
     * {
     *      "status": 403,
     *      "data": {
     *          "error": "Balloon\\Filesystem\\Acl\\Exception\\Forbidden",
     *          "message": "submitted parameters require to have admin privileges",
     *          "code": 41
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (User not found):
     * HTTP/1.1 404 Not Found
     * {
     *      "status": 404,
     *      "data": {
     *          "error": "Balloon\\Server\\User\\Exception",
     *          "message": "requested user was not found",
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
     *          "message": "provide either uid (user id) or uname (username)",
     *          "Code": 0
     *      }
     * }
     */

    /**
     * @apiDefine _getUsers
     *
     * @apiParam (GET Parameter) {string[]} uid Either a single uid (user id) as string or multiple as an array or a single uname (username) as string or multiple usernames as array must be given.
     * @apiParam (GET Parameter) {string[]} uname Either a single uid (userid) as string or multiple as an array or a single uname (username) as string or multiple usernames as array must be given.
     */

    /**
     * Get user instance.
     *
     * @param string $id
     * @param string $uname
     * @param bool   $require_admin
     *
     * @return User
     */
    public function _getUser(?string $id = null, ?string $uname = null, bool $require_admin = false)
    {
        if (null !== $id || null !== $uname || true === $require_admin) {
            if ($this->user->isAdmin()) {
                if (null !== $id && null !== $uname) {
                    throw new InvalidArgumentException('provide either uid (user id) or uname (username)');
                }

                if (null !== $id) {
                    return $this->server->getUserById(new ObjectId($id));
                }

                return $this->server->getUserByName($uname);
            }

            throw new ForbiddenException(
                    'submitted parameters require to have admin privileges',
                    ForbiddenException::ADMIN_PRIV_REQUIRED
                );
        }

        return $this->user;
    }

    /**
     * @api {get} /api/v2/users/whoami Who am I?
     * @apiVersion 2.0.0
     * @apiName getWhoami
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get the username of the authenticated user
     * If you want to receive your own username you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/users/whoami?pretty"
     *
     * @apiSuccess {string} id User ID
     * @apiSuccess {string} name Username
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "id": "544627ed3c58891f058b4611",
     *     "name": "peter.meier"
     * }
     *
     * @param string $id
     * @param string $uname
     *
     * @return Response
     */
    public function getWhoami(array $attributes = []): Response
    {
        $result = $this->decorator->decorate($this->_getUser(), $attributes);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/users/:id/node-attribute-summary Node attribute summary
     * @apiVersion 2.0.0
     * @apiName getNodeAttributeSummary
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get summary and usage of specific node attributes
     * If you want to receive your own node summary you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/users/node-attribute-summary?pretty"
     * curl -XGET "https://SERVER/api/v2/users/544627ed3c58891f058b4611/node-attribute-summary?pretty"
     * curl -XGET "https://SERVER/api/v2/users/node-attribute-summary?uname=loginuser&pretty"
     *
     * @param string $id
     * @param string $uname
     * @param string $attributes
     * @param int    $limit
     *
     * @return Response
     */
    public function getNodeAttributeSummary(?string $id = null, ?string $uname = null, array $attributes = [], int $limit = 25): Response
    {
        $result = $this->_getUser($id, $uname)->getNodeAttributeSummary($attributes, $limit);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/users/:id/groups Group membership
     * @apiVersion 2.0.0
     * @apiName getGroups
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get all user groups
     * If you want to receive your own groups you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/users/groups?pretty"
     * curl -XGET "https://SERVER/api/v2/users/544627ed3c58891f058b4611/groups?pretty"
     * curl -XGET "https://SERVER/api/v2/users/groups?uname=loginuser&pretty"
     *
     * @apiSuccess {object[]} - List of groups
     * @apiSuccess {string} -.id Group ID
     * @apiSuccess {string} -.name Name
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "id": "544627ed3c58891f058b4611",
     *      "name": "group"
     *  }
     * ]
     *
     * @param string $id
     * @param string $uname
     */
    public function getGroups(?string $id = null, ?string $uname = null, array $attributes = []): Response
    {
        $body = [];

        foreach ($this->_getUser($id, $uname)->getGroups() as $group) {
            $body[] = $this->decorator->decorate($group, $attributes);
        }

        return (new Response())->setCode(200)->setBody($body);
    }

    /**
     * @api {get} /api/v2/users/:id/quota-usage Quota usage
     * @apiVersion 2.0.0
     * @apiName getQuotaUsage
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get user quota usage (including hard,soft,used and available space).
     * If you want to receive your own quota you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/users/quota-usage?pretty"
     * curl -XGET "https://SERVER/api/v2/users/544627ed3c58891f058b4611/quota-usage?pretty"
     * curl -XGET "https://SERVER/api/v2/users/quota-usage?uname=loginuser&pretty"
     *
     * @apiSuccess {number} used Used quota in bytes
     * @apiSuccess {number} available Quota left in bytes
     * @apiSuccess {number} hard_quota Hard quota in bytes
     * @apiSuccess {number} soft_quota Soft quota (Warning) in bytes
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "used": 15543092,
     *      "available": 5353166028,
     *      "hard_quota": 5368709120,
     *      "soft_quota": 5368709120
     * }
     *
     * @param string $id
     * @param string $uname
     *
     * @return Response
     */
    public function getQuotaUsage(?string $id = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($id, $uname)->getQuotaUsage();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/users/:id User attributes
     * @apiVersion 2.0.0
     * @apiName get
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get all user attributes including username, mail, id,....
     * If you want to receive your own attributes you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/users/attributes?pretty"
     * curl -XGET "https://SERVER/api/v2/users/544627ed3c58891f058b4611/attributes?pretty"
     * curl -XGET "https://SERVER/api/v2/users/attributes?uname=loginser&pretty"
     *
     * @apiSuccess (200 OK) {string} id User ID
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b4611",
     *      "name": "loginuser"
     * }
     *
     * @param string $id
     * @param string $uname
     * @param string $attributes
     *
     * @return Response
     */
    public function get(?string $id = null, ?string $uname = null, array $filter = [], array $attributes = []): Response
    {
        if ($id === null && $uname === null) {
            $result = [];
            foreach ($this->server->getUsers($filter) as $user) {
                $result[] = $this->decorator->decorate($user, $attributes);
            }
        } else {
            $result = $this->decorator->decorate($this->_getUser($id, $uname), $attributes);
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/users/:id/avatar Get user avatar
     * @apiVersion 2.0.0
     * @apiName getAvatar
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get users avaatr
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/users/avatar?pretty"
     * curl -XGET "https://SERVER/api/v2/users/544627ed3c58891f058b4611/avatar?pretty"
     * curl -XGET "https://SERVER/api/v2/users/avatar?uname=loginser&pretty"
     *
     * @apiSuccess (200 OK) {string} id User ID
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param string $id
     * @param string $uname
     *
     * @return Response
     */
    public function getAvatar(?string $id = null, ?string $uname = null): Response
    {
        $avatar = $this->_getUser($id, $uname)->getAttributes()['avatar'];
        if ($avatar instanceof Binary) {
            return (new Response())->setCode(200)->setBody(base64_encode($avatar->getData()));
        }

        return (new Response())->setCode(404);
    }

    /**
     * @api {head} /api/v2/users/:id User exists?
     * @apiVersion 2.0.0
     * @apiName head
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Check if user account exists
     *
     * @apiExample Example usage:
     * curl -XHEAD "https://SERVER/api/v2/user"
     * curl -XHEAD "https://SERVER/api/v2/users/544627ed3c58891f058b4611"
     * curl -XHEAD "https://SERVER/api/v2/user?uname=loginuser"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $id
     *
     * @return Response
     */
    public function head(?string $id = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($id, $uname, true);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/users Create user
     * @apiVersion 2.0.0
     * @apiName post
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Create user
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/user"
     *
     * @apiParam (POST Parameter) {string} username Name of the new user
     * @apiParam (POST Parameter) {string} [attributes.password] Password
     * @apiParam (POST Parameter) {string} [attributes.mail] Mail address
     * @apiParam (POST Parameter) {string} [attributes.avatar] Avatar image base64 encoded
     * @apiParam (POST Parameter) {string} [attributes.namespace] User namespace
     * @apiParam (POST Parameter) {number} [attributes.hard_quota] The new hard quota in bytes (Unlimited by default)
     * @apiParam (POST Parameter) {number} [attributes.soft_quota] The new soft quota in bytes (Unlimited by default)
     *
     * @apiSuccess (200 OK) {string} id User ID
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "id": "544627ed3c58891f058b4633"
     * }
     *
     * @param string $username
     * @param array  $attributes
     *
     * @return Response
     */
    public function post(string $username, array $attributes = []): Response
    {
        if (isset($attributes['avatar'])) {
            $attributes['avatar'] = new Binary(base64_decode($attributes['avatar']), Binary::TYPE_GENERIC);
        }

        $id = $this->server->addUser($username, $attributes);
        $result = $this->decorator->decorate($this->server->getUserById($id));

        return (new Response())->setBody($result)->setCode(201);
    }

    /**
     * @api {patch} /api/v2/users/:id Change attributes
     * @apiVersion 2.0.0
     * @apiName patch
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Set attributes for user
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/users/attributes" -d '{"attributes": ["mail": "user@example.com"]}'
     * curl -XPOST "https://SERVER/api/v2/users/attributes?{%22attributes%22:[%22mail%22:%22user@example.com%22]}""
     * curl -XPOST "https://SERVER/api/v2/users/544627ed3c58891f058b4611/attributes" -d '{"attributes": ["admin": "false"]}'
     * curl -XPOST "https://SERVER/api/v2/users/quota?uname=loginuser"  -d '{"attributes": ["admin": "false"]}'
     *
     * @apiParam (POST Parameter) {[]} attributes
     * @apiParam (POST Parameter) {number} attributes.hard_quota The new hard quota in bytes
     * @apiParam (POST Parameter) {number} attributes.soft_quota The new soft quota in bytes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param string $uname
     * @param string $id
     * @param array  $attributes
     *
     * @return Response
     */
    public function patch(array $attributes = [], ?string $id = null, ?string $uname = null): Response
    {
        if (isset($attributes['avatar'])) {
            $attributes['avatar'] = new Binary(base64_decode($attributes['avatar']), Binary::TYPE_GENERIC);
        }

        $user = $this->_getUser($id, $uname, true)->setAttributes($attributes);
        $result = $this->decorator->decorate($user);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {delete} /api/v2/users/:id Delete user
     * @apiVersion 2.0.0
     * @apiName delete
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Delete user account, this will also remove any data owned by the user. If force is false, all data gets moved to the trash. If force
     * is true all data owned by the user gets ereased.
     *
     * @apiExample Example usage:
     * curl -XDELETE "https://SERVER/api/v2/users/544627ed3c58891f058b4611?force=1"
     * curl -XDELETE "https://SERVER/api/v2/user?uname=loginuser"
     *
     * @apiParam (GET Parameter) {bool} [force=false] Per default the user account will be disabled, if force is set
     * the user account gets removed completely.
     *
     * @apiErrorExample {json} Error-Response (Can not delete yourself):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Server\\User\\Exception",
     *          "message": "requested user was not found"
     *      }
     * }
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $id
     * @param bool   $force
     *
     * @return Response
     */
    public function delete(?string $id = null, ?string $uname = null, bool $force = false): Response
    {
        $user = $this->_getUser($id, $uname, true);

        if ($user->getId() === $this->user->getId()) {
            throw new Exception(
                'can not delete yourself',
                Exception::CAN_NOT_DELETE_OWN_ACCOUNT
            );
        }

        $user->delete($force);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/users/:id/undelete Enable user account
     * @apiVersion 2.0.0
     * @apiName postUndelete
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Apiore user account. This endpoint does not restore any data, it only does reactivate an existing user account.
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/users/544627ed3c58891f058b4611/undelete"
     * curl -XPOST "https://SERVER/api/v2/users/undelete?user=loginuser"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $id
     *
     * @return Response
     */
    public function postUndelete(?string $id = null, ?string $uname = null): Response
    {
        $this->_getUser($id, $uname, true)->undelete();

        return (new Response())->setCode(204);
    }
}
