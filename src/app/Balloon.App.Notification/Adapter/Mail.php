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

use Balloon\Async\Mail as MailJob;
use Balloon\Async;
use Psr\Log\LoggerInterface;
use Zend\Mail\Message;
use Balloon\Server\User;

class Mail implements AdapterInterface
{
    /**
     * Sender address
     *
     * @var string
     */
    protected $sender_address = 'balloon@local';


    /**
     * Sender name
     *
     * @var string
     */
    protected $sender_name = 'balloon';


    /**
     * Constructor
     *
     * @param Async $async
     * @param LoggerInterface $logger
     * @param Iterable $config
     */
    public function __construct(Async $async, LoggerInterface $logger, ?Iterable $config=[])
    {
        $this->async = $async;
        $this->logger = $logger;
    }


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function setOptions(?Iterable $config=[]): AdapterInterface
    {
        if($config === null) {
            return $this;
        }

        foreach($config as $option => $value) {
            switch($option) {
                case 'sender_address':
                case 'sender_name':
                    $this->{$option} = $value;
                break;
                default:
                    throw new Exception('unknown option '.$option);
            }
        }

        return $this;
    }


    /**
     * {@inheritDoc}
     */
    public function notify(array $receiver, ?User $sender, string $subject, string $body, array $context=[]): bool
    {
        $mail = new Message();
        $mail->setBody($body);

        if($sender === null) {
            $mail->setFrom($this->sender_address, $this->sender_name);
        } else {
            $mail->setFrom($this->sender_address, $sender->getAttribute('username'));
        }

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

        if(count($mail->getBcc()) === 0) {
            $this->logger->warning('skip mail notifcation ['.$subject.'], no receiver available', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->async->addJob(MailJob::class, ['mail' => $mail->toString()]);
    }
}
