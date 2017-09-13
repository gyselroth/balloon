<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Server;
use \MongoDB\Database;
use \Zend\Mail\Message;
use \Zend\Mail\Transport\Sendmail;

class Mail extends AbstractJob
{
    /**
     * Transport
     *
     * @var string
     */
    protected $transport = Sendmail::class;


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return Mail
     */
    public function setOptions(?Iterable $config=null): Mail
    {
        if (isset($config['mail']) && isset($config['mail']['transport'])) {
            $this->transport = $config->mail->transport;
        }

        return $this;
    }


    /**
     * Run job
     *
     * @param   Server $server
     * @param   Logger $logger
     * @return  bool
     */
    public function run(Server $server, Logger $logger): bool
    {
        $mail = Message::fromString($this->data['mail']);
        $logger->debug('send mail ['.$mail->getSubject().']', [
            'category' => get_class($this),
            'params'   => ['to' => (array)$mail->getTo()]
        ]);

        $transport = new $this->transport();
        $transport->send($mail);
        return true;
    }
}
