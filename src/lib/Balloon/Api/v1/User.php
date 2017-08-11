<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api\v1;

use \Balloon\Exception;
use \Balloon\Controller;
use \Balloon\User as CoreUser;
use \Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class User extends Controller
{
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
     * Get user instance
     *
     * @param   string $uid
     * @param   string $uname
     * @return  User
     */
    public function _getUser(?string $uid=null, ?string $uname=null)
    {
        if ($uid !== null || $uname !== null) {
            if ($this->user->isAdmin()) {
                if ($uid !== null && $uname !== null) {
                    throw new Exception\InvalidArgument('provide either uid (user id) or uname (username)');
                }

                if ($uid !== null) {
                    $user = new ObjectId($uid);
                } else {
                    $user = $uname;
                }
                
                try {
                    $user = new CoreUser($user, $this->logger, $this->fs, false, true);
                    $this->fs->setUser($user);
                    return $user;
                } catch (\Exception $e) {
                    throw new Exception\NotFound('requested user was not found',
                        Exception\NotFound::USER_NOT_FOUND
                    );
                }
            } else {
                throw new Exception\Forbidden('submitted parameters require to have admin privileges',
                    Exception\Forbidden::ADMIN_PRIV_REQUIRED
                );
            }
        } else {
            return $this->user;
        }
    }


    /**
     * @api {get} /api/v1/user/is-admin Is Admin?
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @return Response
     */
    public function getIsAdmin(?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname)->isAdmin();
        return (new Response())->setCode(200)->setBody($result);
    }

    
    /**
     * @api {get} /api/v1/user/whoami Who am I?
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @return Response
     */
    public function getWhoami(?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname)->getUsername();
        return (new Response())->setCode(200)->setBody($result);
    }
   
 
    /**
     * @api {get} /api/v1/user/node-attribute-summary Node attribute summary
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @param  string $attributes
     * @param  int $limit
     * @return Response
     */
    public function getNodeAttributeSummary(?string $uid=null, ?string $uname=null, array $attributes=[], int $limit=25): Response
    {
        $result = $this->_getUser($uid, $uname)->getNodeAttributeSummary($attributes, $limit);
        return (new Response())->setCode(200)->setBody($result);
    }

    
    /**
     * @api {get} /api/v1/user/groups Group membership
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @return void
     */
    public function getGroups(?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname)->getGroups();
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {get} /api/v1/user/shares Share membership
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @return Response
     */
    public function getShares(?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname)->getShares();
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {get} /api/v1/user/quota-usage Quota usage
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @return Response
     */
    public function getQuotaUsage(?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname)->getQuotaUsage();
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {get} /api/v1/user/attributes User attributes
     * @apiVersion 1.0.6
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
     * @param  string $uid
     * @param  string $uname
     * @param  string $attributes
     * @return Response
     */
    public function getAttributes(?string $uid=null, ?string $uname=null, array $attributes=[]): Response
    {
        $result = $this->_getUser($uid, $uname)->getAttribute($attributes);
        return (new Response())->setCode(200)->setBody($result);
    }
}
