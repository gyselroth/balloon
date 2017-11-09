<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Api\v1;

use Balloon\Api\Controller;
use Balloon\App\Notification\Notifier;
use Balloon\Server;
use Micro\Http\Response;
use Balloon\Filesystem\Node\File;

class Notification
{
    /**
     * Notifier
     *
     * @var Notifier
     */
    protected $notifier;


    /**
     * User
     *
     * @var User
     */
    protected $user;


    /**
     * Constructor
     *
     * @param Notifier
     */
    public function __construct(Notifier $notifier, Server $server)
    {
        $this->notifier = $notifier;
        $this->user = $server->getUser();
    }


    /**
     * @api {get} /api/v1/notification Get notifications
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Fetch my nofitifications
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/user/notification"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [{
     *          "message": "Hi there, this is a notification"
     *      }]
     * }
     */
    public function get(): Response
    {
        $notifications = $this->user->getAppAttribute('Balloon\\App\\Notification', 'notifications');
        return (new Response())->setCode(200)->setBody($notifications);
    }


    /**
     * @api {get} /api/v1/notification Get notifications
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Fetch my nofitifications
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/user/notification"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function delete(int $id): Response
    {
        $notifications = $this->user->getAppAttribute('Balloon\\App\\Notification', 'notifications');
        return (new Response())->setCode(200)->setBody($notifications);
    }


    /**
     * @api {post} /api/v1/user/notification/broadcast Post a notification to all users (or to a bunch of users)
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup App\Notification
     * @apiPermission admin
     * @apiDescription Send notification
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v1/user/notification/broadcast"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function postBroadcast(string $message): Response
    {
        //if admin
    }


    /**
     * @api {get} /api/v1/notification Get notifications
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Fetch my nofitifications
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/user/notification"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function postMail(array $mail)
    {

    }
}
