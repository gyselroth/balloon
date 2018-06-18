<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\User\Exception;
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
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
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
     *
     * @return User
     */
    public function _getUser(?string $id = null, ?string $uname = null, bool $require_admin = false)
    {
        if (null !== $id || null !== $uname || true === $require_admin) {
            if ($this->user->isAdmin()) {
                if (null !== $id && null !== $uname) {
                    throw new Exception\InvalidArgument(
                        'provide either id (user id) or uname (username)',
                        Exception\InvalidArgument::IDENTIFIER_NOT_UNIQUE
                    );
                }

                if (null !== $id) {
                    return $this->server->getUserById(new ObjectId($id));
                }

                return $this->server->getUserByName($uname);
            }

            throw new Exception\NotAdmin('submitted parameters require admin privileges');
        }

        return $this->user;
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

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
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
     */
    public function getShares(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->getShares();

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
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
     */
    public function getQuotaUsage(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)->getQuotaUsage();

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
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
     */
    public function getAttributes(?string $uid = null, ?string $uname = null, array $attributes = []): Response
    {
        $result = $this->decorator->decorate($this->_getUser($uid, $uname), $attributes);

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
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
     */
    public function head(?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname);

        return (new Response())->setCode(204);
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
     * curl -XGET "https://SERVER/api/v2/user/whoami?pretty"
     *
     * @apiSuccess {number} code HTTP status code
     * @apiSuccess {string} data Username
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "code": 200,
     *     "data": "user"
     * }
     */
    public function getWhoami(array $attributes = []): Response
    {
        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $this->_getUser()->getUsername(),
        ]);
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
     * @param string $id
     * @param string $uname
     * @param string $attributes
     */
    public function getNodeAttributeSummary(?string $id = null, ?string $uname = null, array $attributes = [], int $limit = 25): Response
    {
        $result = $this->_getUser($id, $uname)->getNodeAttributeSummary($attributes, $limit);

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * @api {post} /api/v1/user/quota?uid=:uid Set quota
     * @apiVersion 1.0.0
     * @apiName postQuota
     * @apiUse _getUser
     * @apiGroup User
     * @apiPermission admin
     * @apiDescription Set quota for user
     *
     * @apiExample Example usage:
     * curl -XPOST -d hard=10000000 -d soft=999999 "https://SERVER/api/v1/user/quota"
     * curl -XPOST -d hard=10000000 -d soft=999999 "https://SERVER/api/v1/user/544627ed3c58891f058b4611/quota"
     * curl -XPOST -d hard=10000000 -d soft=999999 "https://SERVER/api/v1/user/quota?uname=loginuser"
     *
     * @apiParam (GET Parameter) {number} hard The new hard quota in bytes
     * @apiParam (GET Parameter) {number} soft The new soft quota in bytes
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $uname
     * @param string $uid
     */
    public function postQuota(int $hard, int $soft, ?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname, true)
            ->setHardQuota($hard)
            ->setSoftQuota($soft);

        return (new Response())->setCode(204);
    }
}
