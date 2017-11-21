<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Psr\Log\LoggerInterface;
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
     * Constructor.
     *
     * @param TransportInterface $transport
     * @param LoggerInterface    $logger
     */
    public function __construct(TransportInterface $transport, LoggerInterface $logger)
    {
        $this->transport = $transport;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $mail = Message::fromString($this->data['mail']);

        $this->logger->debug('send mail ['.$mail->getSubject().']', [
            'category' => get_class($this),
            'params' => ['to' => (array) $mail->getTo()],
        ]);

        $this->transport->send($mail);

        return true;
    }
}
