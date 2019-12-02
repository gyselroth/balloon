<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Api\v2;

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
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use function MongoDB\BSON\toPHP;
use Psr\Log\LoggerInterface;
use TaskScheduler\Scheduler;
use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

class Notifications
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
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

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
    public function __construct(Notifier $notifier, Server $server, Scheduler $scheduler, LoggerInterface $logger, RoleAttributeDecorator $role_decorator, NodeAttributeDecorator $node_decorator, NotificationAttributeDecorator $notification_decorator)
    {
        $this->notifier = $notifier;
        $this->user = $server->getIdentity();
        $this->fs = $server->getFilesystem();
        $this->server = $server;
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->role_decorator = $role_decorator;
        $this->node_decorator = $node_decorator;
        $this->notification_decorator = $notification_decorator;
    }

    /**
     * Get notifications.
     *
     * @param null|mixed $query
     */
    public function get(?ObjectId $id = null, $query = null, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if ($id !== null) {
            $message = $this->notifier->getNotification($id);
            $result = $this->notification_decorator->decorate($message, $attributes);

            return (new Response())->setCode(200)->setBody($result);
        }

        $result = $this->notifier->getNotifications($this->user, $this->parseQuery($query), $offset, $limit, $total);
        $uri = '/api/v2/notifications';
        $pager = new Pager($this->notification_decorator, $result, $attributes, $offset, $limit, $uri, $total);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Delete notification.
     */
    public function delete(ObjectId $id): Response
    {
        $this->notifier->deleteNotification($id);

        return (new Response())->setCode(204);
    }

    /**
     * Post a notification to a group of users.
     */
    public function post(array $receiver, string $subject, string $body): Response
    {
        $users = $this->server->getUsersById($receiver);

        $message = $this->notifier->compose('user_message', [
            'user_subject' => $subject,
            'user_body' => $body,
        ]);

        $this->notifier->notify($users, $this->user, $message);

        return (new Response())->setCode(202);
    }

    /**
     * Post a notification to all users.
     */
    public function postBroadcast(string $subject, string $body): Response
    {
        if (!$this->user->isAdmin()) {
            throw new ForbiddenException('submitted parameters require to have admin privileges', ForbiddenException::ADMIN_PRIV_REQUIRED);
        }

        $users = $this->server->getUsers();
        $message = $this->notifier->compose('user_message', [
            'user_subject' => $subject,
            'user_body' => $body,
        ]);

        $this->notifier->notify($users, $this->user, $message);

        return (new Response())->setCode(202);
    }

    /**
     * Send a mail.
     */
    public function postMail(array $receiver, string $subject, string $body)
    {
        $message = $this->notifier->compose('user_message', [
            'user_subject' => $subject,
            'user_body' => $body,
        ]);

        $html = new MimePart($message->renderTemplate('mail_html.phtml'));
        $html->type = 'text/html';
        $html->setCharset('utf-8');

        $plain = new MimePart($message->renderTemplate('mail_plain.phtml'));
        $plain->type = 'text/plain';
        $plain->setCharset('utf-8');
        $body = new MimeMessage();
        $body->setParts([$html, $plain]);

        $mail = (new Message())
          ->setSubject($message->getSubject())
          ->setBody($body)
          ->setEncoding('UTF-8');

        $type = $mail->getHeaders()->get('Content-Type');
        $type->setType('multipart/alternative');

        foreach ($receiver as $address) {
            $mail->setTo($address);
            $this->scheduler->addJob(Mail::class, $mail->toString());
        }

        return (new Response())->setCode(202);
    }

    /**
     * Parse query.
     */
    protected function parseQuery($query): array
    {
        if ($query === null) {
            $query = [];
        } elseif (is_string($query)) {
            $query = toPHP(fromJSON($query), [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);
        }

        return (array) $query;
    }
}
