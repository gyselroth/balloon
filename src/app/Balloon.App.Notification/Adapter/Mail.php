<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Adapter;

use Balloon\App\Notification\MessageInterface;
use Balloon\Async\Mail as MailJob;
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
                'category' => static::class,
            ]);

            return false;
        }

        $data['receiver'] = $receiver->getId();
        $data['subject'] = $message->getSubject($receiver);
        $data['message'] = $message->getContext();

        $this->scheduler->addJob(MailJob::class, $data, [
            Scheduler::OPTION_RETRY => 1,
        ]);

        return true;
    }
}
