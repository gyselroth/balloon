<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Api\Latest;

use Balloon\App\Api\Controller;
use Balloon\App\Notification\Notifier;
use Balloon\Async\Mail;
use Balloon\Filesystem;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\User;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;
use Zend\Mail\Message;

class Notification extends Controller
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
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Constructor.
     *
     * @param Notifier           $notifier
     * @param Server             $server
     * @param Async              $async
     * @param LoggerInterface    $logger
     * @param AttributeDecorator $decorator
     */
    public function __construct(Notifier $notifier, Server $server, Async $async, LoggerInterface $logger, AttributeDecorator $decorator)
    {
        $this->notifier = $notifier;
        $this->user = $server->getIdentity();
        $this->fs = $server->getFilesystem();
        $this->server = $server;
        $this->async = $async;
        $this->logger = $logger;
        $this->decorator = $decorator;
    }

    /**
     * @api {get} /api/v2/notification Get notifications
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
        foreach ($this->notifier->getNotifications($this->user) as $message) {
            $note = $message;
            $note['id'] = (string) $note['_id'];
            unset($note['_id'], $note['receiver']);

            $note['sender'] = $this->decorator->decorate($this->server->getUserById($note['sender']), ['id', 'username']);
            $body[] = $note;
        }

        return (new Response())->setCode(200)->setBody($body);
    }

    /**
     * @api {delete} /api/v2/notification Delete notification
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
    public function delete(string $id): Response
    {
        $this->notifier->deleteNotification($this->user, new ObjectId($id));

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/notification Post a notification to a group of users
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
     * HTTP/1.1 204 No Content
     */
    public function post(array $receiver, string $subject, string $body): Response
    {
        $users = $this->server->getUsersById($receiver);
        $this->notifier->notify($receiver, $this->user, $subject, $body);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/notification/broadcast Post a notification to all users (or to a bunch of users)
     * @apiVersion 2.0.0
     * @apiName postBroadcast
     * @apiGroup App\Notification
     * @apiPermission admin
     * @apiDescription Send notification
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v2/notification/broadcast"
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
        $this->notifier->notify($users, $this->user, $subject, $body);

        return (new Response())->setCode(202);
    }

    /**
     * @api {post} /api/v2/notification/mail Send a mail
     * @apiVersion 2.0.0
     * @apiName postMail
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Send mail
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/notification/mail"
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

    /**
     * @api {post} /api/v2/notification/subscribe Subscribe for node update
     * @apiVersion 2.0.0
     * @apiName postSubscribe
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Receive node updates
     * @apiUse _getNodes
     * @apiUse _multiError
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v2/notification/subscribe"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param null|mixed $id
     * @param null|mixed $p
     */
    public function postSubscribe($id = null, $p = null, bool $subscribe = true, bool $exclude_me = true, bool $recursive = false)
    {
        if (is_array($id) || is_array($p)) {
            foreach ($this->fs->findNodesById($id) as $node) {
                try {
                    $this->notifier->subscribeNode($this->fs->getNode($id, $p), $subscribe);
                } catch (\Exception $e) {
                    $failures[] = [
                        'id' => (string) $node->getId(),
                        'name' => $node->getName(),
                        'error' => get_class($e),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ];

                    $this->logger->debug('failed subscribe node in multi node request ['.$node->getId().']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            }

            if (empty($failures)) {
                return (new Response())->setCode(204);
            }

            return (new Response())->setcode(400)->setBody($failures);
        }

        $this->notifier->subscribeNode($this->fs->getNode($id, $p), $subscribe, $exclude_me, $recursive);

        return (new Response())->setCode(204);
    }
}
