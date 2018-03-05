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
use Balloon\Async\Mail as MailJob;
use Balloon\Server\User;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;
use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

class Mail implements AdapterInterface
{
    /**
     * Sender address.
     *
     * @var string
     */
    protected $sender_address = 'balloon@local';

    /**
     * Sender name.
     *
     * @var string
     */
    protected $sender_name = 'balloon';

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
     * Constructor.
     *
     * @param Async           $async
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(Async $async, LoggerInterface $logger, ?Iterable $config = [])
    {
        $this->async = $async;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return AdapterInterface
     */
    public function setOptions(?Iterable $config = []): AdapterInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'sender_address':
                case 'sender_name':
                    $this->{$option} = $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$option.' given');
            }
        }

        return $this;
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

        $html = new MimePart($message->getMailBody($receiver));
        $html->type = 'text/html';
        $body = new MimeMessage();
        $body->addPart($html);

        $mail = (new Message())
          ->setSubject($message->getSubject($receiver))
          ->setBody($body)
          ->setTo($address);

        if (null === $sender) {
            $mail->setFrom($this->sender_address, $this->sender_name);
        } else {
            $mail->setFrom($this->sender_address, $sender->getAttributes()['username']);
        }

        $this->async->addJob(MailJob::class, $mail->toString(), [
            Async::OPTION_RETRY => 1,
        ]);

        return true;
    }
}
