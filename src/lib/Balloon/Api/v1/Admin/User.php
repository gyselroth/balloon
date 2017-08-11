<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api\v1\Admin;

use \Balloon\Exception;
use \Balloon\Api\v1\User as SimpleUser;
use \Micro\Http\Response;

class User extends SimpleUser
{
    /**
     * @api {head} /api/v1/user?uid=:uid User exists?
     * @apiVersion 1.0.6
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
     * @param   string $uname
     * @param   string $uid
     * @return  Response
     */
    public function head(?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname);
        return (new Response())->setCode(204);
    }
    

    /**
     * @api {post} /api/v1/user/quota?uid=:uid Set quota
     * @apiVersion 1.0.6
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
     * @param   string $uname
     * @param   string $uid
     * @param   int $hard
     * @param   int $soft
     * @return  Response
     */
    public function postQuota(int $hard, int $soft, ?string $uid=null, ?string $uname=null): Response
    {
        $result = $this->_getUser($uid, $uname)
            ->setHardQuota($hard)
            ->setSoftQuota($soft);
        return (new Response())->setCode(204);
    }
    

    /**
     * @api {delete} /api/v1/user?uid=:uid Delete user
     * @apiVersion 1.0.6
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
     * @param   string $uname
     * @param   string $uid
     * @param   bool $force
     * @return  Response
     */
    public function delete(?string $uid=null, ?string $uname=null, bool $force=false): Response
    {
        $user = $this->_getUser($uid, $uname);

        if ($user->getId() == $this->user->getId()) {
            throw new Exception\Conflict('can not delete yourself',
                Exception\Conflict::CAN_NOT_DELETE_OWN_ACCOUNT
            );
        }

        $user->delete($force);
        return (new Response())->setCode(204);
    }

    
    /**
     * @api {post} /api/v1/user/undelete?uid=:uid Apiore user
     * @apiVersion 1.0.6
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
     * @param   string $uname
     * @param   string $uid
     * @return  Response
     */
    public function postUndelete(?string $uid=null, ?string $uname=null): Response
    {
        $this->_getUser($uid, $uname)->undelete();
        return (new Response())->setCode(204);
    }
}
