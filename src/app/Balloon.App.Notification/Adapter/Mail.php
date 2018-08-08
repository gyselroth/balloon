<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Adapter;

use Balloon\App\Notification\MessageInterface;
use Balloon\Scheduler\Mail as MailJob;
use Balloon\Server\User;
use Psr\Log\LoggerInterface;
use TaskScheduler\Scheduler;
use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

class Mail implements AdapterInterface
{
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
     * Constructor.
     */
    public function __construct(Scheduler $scheduler, LoggerInterface $logger)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(User $receiver, ?User $sender, MessageInterface $message, array $context = []): bool
    {
        $address = $receiver->getAttributes()['mail'];
        if (null === $address) {
            $this->logger->debug('skip mail notifcation for user ['.$receiver->getId().'], user does not have a valid mail address', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $html = new MimePart($message->renderTemplate('mail_html.phtml', $receiver));
        $html->type = 'text/html';
        $html->setCharset('utf-8');

        $plain = new MimePart($message->renderTemplate('mail_plain.phtml', $receiver));
        $plain->type = 'text/plain';
        $plain->setCharset('utf-8');
        $body = new MimeMessage();
        $body->setParts([$html, $plain]);

        $mail = (new Message())
          ->setSubject($message->getSubject($receiver))
          ->setBody($body)
          ->setTo($address)
          ->setEncoding('UTF-8');

        $type = $mail->getHeaders()->get('Content-Type');
        $type->setType('multipart/alternative');

        $this->scheduler->addJob(MailJob::class, $mail->toString(), [
            Scheduler::OPTION_RETRY => 1,
        ]);

        return true;
    }
}
