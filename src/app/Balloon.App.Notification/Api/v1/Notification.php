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

use Balloon\App\Notification\Notifier;
use TaskScheduler\Async;
use Balloon\Async\Mail;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Http\Response;
use Zend\Mail\Message;
use Balloon\Exception;
use MongoDB\BSON\ObjectId;

class Notification
{
    /**
     * Notifier.
     *
     * @var Notifier
     */
    protected $notifier;

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
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param Notifier $notifier
     * @param Server   $server
     * @param Async    $async
     */
    public function __construct(Notifier $notifier, Server $server, Async $async)
    {
        $this->notifier = $notifier;
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->async = $async;
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
        $body = [];
        foreach($this->notifier->getNotifications($this->user) as $message) {
            $note = $message;
            $note['id'] = (string)$note['_id'];
            unset($note['_id']);
            unset($note['receiver']);

            $note['sender'] = $this->server->getUserById($note['sender'])->getAttribute([
                'id',
                'username'
            ]);

            $body[] = $note;
        }

        return (new Response())->setCode(200)->setBody($body);
    }

    /**
     * @api {delete} /api/v1/user/notification Delete notification
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
    public function delete(string $id): Response
    {
        $this->notifier->deleteNotification($this->user, new ObjectId($id));
        return (new Response())->setCode(204);
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
    public function post(array $receiver, string $subject, string $body): Response
    {
        $users = $this->server->getUsersById($receiver);
        $this->notifier->notify($receiver, $this->user, $subject, $body);

        return (new Response())->setCode(204);
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
    public function postBroadcast(string $subject, string $body): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Exception\Forbidden(
                'submitted parameters require to have admin privileges',
                    Exception\Forbidden::ADMIN_PRIV_REQUIRED
                );
        }

        $users = $this->server->getUsersByFilter([]);
        $this->notifier->notify($users, $this->user, $subject, $body);

        return (new Response())->setCode(204);
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
    public function postMail(array $receiver, string $subject, string $body)
    {
        $mail = new Message();
        $mail->setBody($body);
        $mail->setFrom($this->user->getAttribute('username'), $this->user->getAttribute('mail'));
        $mail->setSubject($subject);
        $mail->setBcc($receiver);
        $this->async->addJob(Mail::class, ['mail' => $mail->toString()]);

        return (new Response())->setCode(204);
    }
}
