<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\App\Notification\Notifier;
use Balloon\Server;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;
use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

class Mail extends AbstractJob
{
    /**
     * Transport.
     *
     * @var TransportInterface
     */
    protected $transport;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

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
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Notifier.
     *
     * @var Notifier
     */
    protected $notifier;

    /**
     * Constructor.
     */
    public function __construct(TransportInterface $transport, LoggerInterface $logger, Server $server, Notifier $notifier, ?iterable $config = null)
    {
        $this->transport = $transport;
        $this->notifier = $notifier;
        $this->logger = $logger;
        $this->server = $server;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(?iterable $config = []): self
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
    public function start(): bool
    {
        if (!isset($this->data['receiver']) || null === $this->data['receiver']) {
            $this->logger->debug('skip mail notifcation, receiver is not set', [
                'category' => static::class,
            ]);

            return false;
        }

        $receiver = $this->server->getUserById($this->data['receiver']);
        $address = $receiver->getAttributes()['mail'];

        if (isset($this->data['message']['subscription']['pointer'], $this->data['message']['node']['pointer'])) {
            $subscription = $this->server->getFilesystem()->findNodeById($this->data['message']['subscription']['pointer']);
            $node = $this->server->getFilesystem()->findNodeById($this->data['message']['node']['pointer']);

            $message = $this->notifier->compose('subscription', [
                'subscription' => $subscription,
                'node' => $node,
            ]);

            $html = new MimePart($message->renderTemplate('mail_html.phtml', $receiver));
            $html->type = 'text/html';
            $html->setCharset('utf-8');

            $plain = new MimePart($message->renderTemplate('mail_plain.phtml', $receiver));
            $plain->type = 'text/plain';
            $plain->setCharset('utf-8');
            $body = new MimeMessage();
            $body->setParts([$html, $plain]);

            $mail = (new Message())
                ->setSubject($this->data['subject'])
                ->setBody($body)
                ->setTo($address)
                ->setFrom($this->sender_address, $this->sender_name)
                ->setEncoding('UTF-8');

            $mail->getHeaders()->addHeaderLine('X-Mailer', 'balloon');

            $this->logger->debug('send mail ['.$this->data['subject'].']', [
                'category' => static::class,
            ]);

            $this->transport->send($mail);
            $connection = $this->transport->getConnection();
            $connection->rset();
            $connection->quit();
            $connection->disconnect();

            return true;
        }

        return false;
    }
}
