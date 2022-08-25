<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;
use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;

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
     * Constructor.
     */
    public function __construct(TransportInterface $transport, LoggerInterface $logger, ?iterable $config = null)
    {
        $this->transport = $transport;
        $this->logger = $logger;
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
        $mail = Message::fromString($this->data);
        $mail->setEncoding('UTF-8');
        $mail->setFrom($this->sender_address, $this->sender_name);
        $mail->getHeaders()->addHeaderLine('X-Mailer', 'balloon');

        $this->logger->debug('send mail ['.$mail->getSubject().']', [
            'category' => static::class,
        ]);

        $this->transport->send($mail);
        $connection = $this->transport->getConnection();
        $connection->rset();
        $connection->quit();
        $connection->disconnect();

        return true;
    }
}
