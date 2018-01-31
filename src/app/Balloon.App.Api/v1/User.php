<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\Latest\User as LatestUser;
use Micro\Http\Response;

class User extends LatestUser
{
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
     *
     * @return Response
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
     *
     * @return Response
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
        $result = $this->_getUser($uid, $uname);

        return (new Response())->setCode(204);
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
     * @param int    $hard
     * @param int    $soft
     *
     * @return Response
     */
    public function postQuota(int $hard, int $soft, ?string $uid = null, ?string $uname = null): Response
    {
        $result = $this->_getUser($uid, $uname)
            ->setHardQuota($hard)
            ->setSoftQuota($soft);

        return (new Response())->setCode(204);
    }
}
