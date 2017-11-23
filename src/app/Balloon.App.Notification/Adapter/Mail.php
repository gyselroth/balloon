<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Adapter;

use Balloon\App\Notification\Exception;
use TaskScheduler\Async;
use Balloon\Async\Mail as MailJob;
use Balloon\Server\User;
use Psr\Log\LoggerInterface;
use Zend\Mail\Message;

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
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(array $receiver, ?User $sender, string $subject, string $body, array $context = []): bool
    {
        $mail = new Message();
        $mail->setBody($body);

        if (null === $sender) {
            $mail->setFrom($this->sender_address, $this->sender_name);
        } else {
            $mail->setFrom($this->sender_address, $sender->getAttribute('username'));
        }

        $mail->setTo($mail->getFrom());
        $mail->setSubject($subject);

        foreach ($receiver as $user) {
            $address = $user->getAttribute('mail');
            if (null === $address) {
                $this->logger->debug('skip mail notifcation ['.$subject.'] for user ['.$user->getId().'], user does not have a valid mail address', [
                    'category' => get_class($this),
                ]);

                continue;
            }

            $mail->setBcc($address);
        }

        if (0 === count($mail->getBcc())) {
            $this->logger->warning('skip mail notifcation ['.$subject.'], no receiver available', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->async->addJob(MailJob::class, $mail->toString(), [
            Async::OPTION_RETRY => 2
        ]);
    }
}
