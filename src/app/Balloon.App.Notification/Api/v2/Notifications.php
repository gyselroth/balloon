<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Notification\AttributeDecorator as NotificationAttributeDecorator;
use Balloon\App\Notification\Notifier;
use Balloon\Async\Mail;
use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;
use Zend\Mail\Message;

class Notifications extends Controller
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
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

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
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Role attribute decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    /**
     * Role attribute decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Notification attribute decorator.
     *
     * @var NotificationAttributeDecorator
     */
    protected $notification_decorator;

    /**
     * Constructor.
     */
    public function __construct(Notifier $notifier, Server $server, Async $async, LoggerInterface $logger, RoleAttributeDecorator $role_decorator, NodeAttributeDecorator $node_decorator, NotificationAttributeDecorator $notification_decorator)
    {
        $this->notifier = $notifier;
        $this->user = $server->getIdentity();
        $this->fs = $server->getFilesystem();
        $this->server = $server;
        $this->async = $async;
        $this->logger = $logger;
        $this->role_decorator = $role_decorator;
        $this->node_decorator = $node_decorator;
        $this->notification_decorator = $notification_decorator;
    }

    /**
     * @api {get} /api/v2/notifications Get notifications
     * @apiVersion 2.0.0
     * @apiName get
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Fetch my nofitifications
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/notification"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  "id": "",
     *  "message": "Hi there, this is a notification",
     *  "subject": "Hi",
     *  "sender": {
     *      "id": "",
     *      "name": ""
     *  }
     * ]
     *
     * @param ObjectId $id
     */
    public function get(?ObjectId $id = null, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if ($id !== null) {
            $message = $this->notifier->getNotification($id);
            $result = $this->notification_decorator->decorate($message, $attributes);

            return (new Response())->setCode(200)->setBody($result);
        }

        $result = $this->notifier->getNotifications($this->user, $offset, $limit, $total);
        $uri = '/api/v2/notifications';
        $pager = new Pager($this->notification_decorator, $result, $attributes, $offset, $limit, $uri, $total);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {delete} /api/v2/notifications Delete notification
     * @apiVersion 2.0.0
     * @apiName delete
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Fetch my nofitifications
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/notification"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     */
    public function delete(ObjectId $id): Response
    {
        $this->notifier->deleteNotification($id);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/notifications Post a notification to a group of users
     * @apiVersion 2.0.0
     * @apiName post
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Send notification
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v2/notification"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 202 Accepted
     */
    public function post(array $receiver, string $subject, string $body): Response
    {
        $users = $this->server->getUsersById($receiver);
        $this->notifier->notify($receiver, $this->user, $subject, $body);

        return (new Response())->setCode(202);
    }

    /**
     * @api {post} /api/v2/notifications/broadcast Post a notification to all users (or to a bunch of users)
     * @apiVersion 2.0.0
     * @apiName postBroadcast
     * @apiGroup App\Notification
     * @apiPermission admin
     * @apiDescription Send notification
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v2/notifications/broadcast"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 202 Accepted
     */
    public function postBroadcast(string $subject, string $body): Response
    {
        if (!$this->user->isAdmin()) {
            throw new ForbiddenException(
                'submitted parameters require to have admin privileges',
                    ForbiddenException::ADMIN_PRIV_REQUIRED
                );
        }

        $users = $this->server->getUsers();
        $message = $this->notifier->customMessage($subject, $body);
        $this->notifier->notify($users, $this->user, $message);

        return (new Response())->setCode(202);
    }

    /**
     * @api {post} /api/v2/notifications/mail Send a mail
     * @apiVersion 2.0.0
     * @apiName postMail
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Send mail
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/notifications/mail"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 202 Accepted
     */
    public function postMail(array $receiver, string $subject, string $body)
    {
        $mail = new Message();
        $mail->setBody($body)
          ->setFrom($this->user->getAttributes()['mail'], $this->user->getAttributes()['username'])
          ->setSubject($subject)
          ->setTo($this->user->getAttributes()['mail'], 'Undisclosed Recipients')
          ->setBcc($receiver);
        $this->async->addJob(Mail::class, $mail->toString());

        return (new Response())->setCode(202);
    }
}
