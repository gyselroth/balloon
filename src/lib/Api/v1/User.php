<?php

declare(strict_types=1);

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

class User
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
     * Initialize.
     *
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->user = $server->getIdentity();
        $this->server = $server;
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
     *          "error": "Balloon\\Exception\\Forbidden",
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
     *          "error": "Balloon\\Exception\\NotFound",
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
     * @param string $uid
     * @param string $uname
     * @param bool   $require_admin
     *
     * @return User
     */
    public function _getUser(?string $uid = null, ?string $uname = null, bool $require_admin = false)
    {
        if (null !== $uid || null !== $uname || true === $require_admin) {
            if ($this->user->isAdmin()) {
                if (null !== $uid && null !== $uname) {
                    throw new Exception\InvalidArgument('provide either uid (user id) or uname (username)');
                }

                if (null !== $uid) {
                    return $this->server->getUserById(new ObjectId($uid));
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
     * @api {get} /api/v1/user/is-admin Is Admin?
     * @apiVersion 1.0.0
     * @apiName getIsAdmin
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Check if the authenicated user has admin rights.
     * If you want to check your own admin status you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/is-admin"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/is-admin"
     * curl -XGET "https://SERVER/api/v1/user/is-admin?uname=loginuser"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {boolean} data TRUE if admin
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": true
     * }
     *
     * @param string $uid
     * @param string $uname
     *
     * @return Response
     */
    public function getIsAdmin(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->isAdmin();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/user/whoami Who am I?
     * @apiVersion 1.0.0
     * @apiName getWhoami
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get the username of the authenticated user
     * If you want to receive your own username you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/whoami?pretty"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/whoami?pretty"
     * curl -XGET "https://SERVER/api/v1/user/whoami?uname=loginuser"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {string} data  The username
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": "peter.meier"
     * }
     *
     * @param string $uid
     * @param string $uname
     *
     * @return Response
     */
    public function getWhoami(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->getUsername();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/user/node-attribute-summary Node attribute summary
     * @apiVersion 1.0.0
     * @apiName getNodeAttributeSummary
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get summary and usage of specific node attributes
     * If you want to receive your own node summary you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/node-attribute-summary?pretty"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/node-attribute-summary?pretty"
     * curl -XGET "https://SERVER/api/v1/user/node-attribute-summary?uname=loginuser&pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {string} data  The username
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": [...]
     * }
     *
     * @param string $uid
     * @param string $uname
     * @param string $attributes
     * @param int    $limit
     *
     * @return Response
     */
    public function getNodeAttributeSummary(?string $uid = null, ?string $uname = null, array $attributes = [], int $limit = 25): Response
    {
        $result = $this->_getUser($uid, $uname)->getNodeAttributeSummary($attributes, $limit);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/user/groups Group membership
     * @apiVersion 1.0.0
     * @apiName getGroups
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get all user groups
     * If you want to receive your own groups you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/groups?pretty"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/groups?pretty"
     * curl -XGET "https://SERVER/api/v1/user/groups?uname=loginuser&pretty"
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
     * @param string $uid
     * @param string $uname
     */
    public function getGroups(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->getGroups();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/user/shares Share membership
     * @apiVersion 1.0.0
     * @apiName getShares
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get all shares
     * If you want to receive your own shares (member or owner) you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/shares?pretty"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/shares?pretty"
     * curl -XGET "https://SERVER/api/v1/user/shares?uname=loginuser&pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {string[]} data  All shares with membership
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": [
     *          "shareid1",
     *          "shareid2",
     *     ]
     * }
     *
     * @param string $uid
     * @param string $uname
     *
     * @return Response
     */
    public function getShares(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->getShares();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/user/quota-usage Quota usage
     * @apiVersion 1.0.0
     * @apiName getQuotaUsage
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get user quota usage (including hard,soft,used and available space).
     * If you want to receive your own quota you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/quota-usage?pretty"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/quota-usage?pretty"
     * curl -XGET "https://SERVER/api/v1/user/quota-usage?uname=loginuser&pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object} data Quota stats
     * @apiSuccess {number} data.used Used quota in bytes
     * @apiSuccess {number} data.available Quota left in bytes
     * @apiSuccess {number} data.hard_quota Hard quota in bytes
     * @apiSuccess {number} data.soft_quota Soft quota (Warning) in bytes
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *         "used": 15543092,
     *         "available": 5353166028,
     *         "hard_quota": 5368709120,
     *         "soft_quota": 5368709120
     *     }
     * }
     *
     * @param string $uid
     * @param string $uname
     *
     * @return Response
     */
    public function getQuotaUsage(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->getQuotaUsage();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/user/attributes User attributes
     * @apiVersion 1.0.0
     * @apiName getAttributes
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission none
     * @apiDescription Get all user attributes including username, mail, id,....
     * If you want to receive your own attributes you have to leave the parameters uid and uname empty.
     * Requesting this api with parameter uid or uname requires admin privileges.
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/user/attributes?pretty"
     * curl -XGET "https://SERVER/api/v1/user/544627ed3c58891f058b4611/attributes?pretty"
     * curl -XGET "https://SERVER/api/v1/user/attributes?uname=loginser&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} user attributes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [] //shortened
     * }
     *
     * @param string $uid
     * @param string $uname
     * @param string $attributes
     *
     * @return Response
     */
    public function getAttributes(?string $uid = null, ?string $uname = null, array $attributes = []): Response
    {
        $result = $this->_getUser($uid, $uname)->getAttribute($attributes);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {head} /api/v1/user?uid=:uid User exists?
     * @apiVersion 1.0.0
     * @apiName postQuota
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Check if user account exists
     *
     * @apiExample Example usage:
     * curl -XHEAD "https://SERVER/api/v1/user"
     * curl -XHEAD "https://SERVER/api/v1/user/544627ed3c58891f058b4611"
     * curl -XHEAD "https://SERVER/api/v1/user?uname=loginuser"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $uid
     *
     * @return Response
     */
    public function head(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname, true);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v1/user
     * @apiVersion 1
     * @apiName postUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Create user
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v1/user"
     *
     * @apiParam (POST Parameter) {string} username Name of the new user
     * @apiParam (POST Parameter) {string} mail Mail address of the new user
     * @apiParam (POST Parameter) {string} [namespace] Namespace of the new user
     * @apiParam (POST Parameter) {number} [hard] The new hard quota in bytes
     * @apiParam (POST Parameter) {number} [soft] The new soft quota in bytes
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} user attributes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "status": 201,
     *      "data": "544627ed3c58891f058b4633"
     * }
     *
     * @param string $username
     * @param string $mail
     * @param int    $hard_quota
     * @param int    $soft_quota
     *
     * @return Response
     */
    public function post(string $username, string $mail, ?string $namespace = null, ?string $password = null, int $hard_quota = 10000000, int $soft_quota = 10000000): Response
    {
        $id = $this->server->addUser($username, $password, [
            'mail' => $mail,
            'namespace' => $namespace,
            'hard_quota' => $hard_quota,
            'soft_quota' => $soft_quota,
        ]);

        return (new Response())->setBody((string) $id)->setCode(201);
    }

    /**
     * @api {post} /api/v1/user/attributes?uid=:uid Set attributes
     * @apiVersion 1
     * @apiName postAttributes
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Set attributes for user
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v1/user/attributes" -d '{"attributes": ["mail": "user@example.com"]}'
     * curl -XPOST "https://SERVER/api/v1/user/attributes?{%22attributes%22:[%22mail%22:%22user@example.com%22]}""
     * curl -XPOST "https://SERVER/api/v1/user/544627ed3c58891f058b4611/attributes" -d '{"attributes": ["admin": "false"]}'
     * curl -XPOST "https://SERVER/api/v1/user/quota?uname=loginuser"  -d '{"attributes": ["admin": "false"]}'
     *
     * @apiParam (POST Parameter) {number} hard The new hard quota in bytes
     * @apiParam (POST Parameter) {number} soft The new soft quota in bytes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $uid
     * @param array  $attributes
     *
     * @return Response
     */
    public function postAttributes(array $attributes = [], ?string $uid = null, ?string $uname = null): Response
    {
        $this->_getUser($uid, $uname, true)->setAttribute($attributes)->save(array_keys($attributes));

        return (new Response())->setCode(204);
    }

    /**
     * @api {delete} /api/v1/user?uid=:uid Delete user
     * @apiVersion 1.0.0
     * @apiName delete
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Delete user account, this will also remove any data owned by the user. If force is false, all data gets moved to the trash. If force
     * is true all data owned by the user gets ereased.
     *
     * @apiExample Example usage:
     * curl -XDELETE "https://SERVER/api/v1/user/544627ed3c58891f058b4611?force=1"
     * curl -XDELETE "https://SERVER/api/v1/user?uname=loginuser"
     *
     * @apiParam (GET Parameter) {bool} [force=false] Per default the user account will be disabled, if force is set
     * the user account gets removed completely.
     *
     * @apiErrorExample {json} Error-Response (Can not delete yourself):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "requested user was not found"
     *      }
     * }
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $uid
     * @param bool   $force
     *
     * @return Response
     */
    public function delete(?string $uid = null, ?string $uname = null, bool $force = false): Response
    {
        $user = $this->_getUser($uid, $uname, true);

        if ($user->getId() === $this->user->getId()) {
            throw new Exception\Conflict(
                'can not delete yourself',
                Exception\Conflict::CAN_NOT_DELETE_OWN_ACCOUNT
            );
        }

        $user->delete($force);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v1/user/undelete?uid=:uid Reactivate user account
     * @apiVersion 1.0.0
     * @apiName postUndelete
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Apiore user account. This endpoint does not restore any data, it only does reactivate an existing user account.
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v1/user/544627ed3c58891f058b4611/undelete"
     * curl -XPOST "https://SERVER/api/v1/user/undelete?user=loginuser"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $uid
     *
     * @return Response
     */
    public function postUndelete(?string $uid = null, ?string $uname = null): Response
    {
        $this->_getUser($uid, $uname, true)->undelete();

        return (new Response())->setCode(204);
    }
}
