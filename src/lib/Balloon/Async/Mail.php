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

use Balloon\Server;
use Psr\Log\LoggerInterface;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail;

class Mail extends AbstractJob
{
    /**
     * Transport.
     *
     * @var string
     */
    protected $transport = Sendmail::class;

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Mail
     */
    public function setOptions(?Iterable $config = null): Mail
    {
        if (isset($config['mail'], $config['mail']['transport'])) {
            $this->transport = $config->mail->transport;
        }

        return $this;
    }

    /**
     * Run job.
     *
     * @param Server          $server
     * @param LoggerInterface $logger
     *
     * @return bool
     */
    public function start(Server $server, LoggerInterface $logger): bool
    {
        $mail = Message::fromString($this->data['mail']);
        $logger->debug('send mail ['.$mail->getSubject().']', [
            'category' => get_class($this),
            'params' => ['to' => (array) $mail->getTo()],
        ]);

        $transport = new $this->transport();
        $transport->send($mail);

        return true;
    }
}
