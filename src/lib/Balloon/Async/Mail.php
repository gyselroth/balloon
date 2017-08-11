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
use \Micro\Config;
use \Balloon\Filesystem;
use \MongoDB\Database;
use \Zend\Mail\Message;

class Mail extends AbstractJob
{
    /**
     * Transport
     *
     * @var string
     */
    protected $transport = '\Zend\Mail\Transport\Sendmail';


    /**
     * Set options
     *
     * @param  Config $config
     * @return MailNotification
     */
    public function setOptions(Config $config): Mail
    {
        if (isset($config['mail']) && isset($config['mail']['transport'])) {
            $this->transport = $config->mail->transport;
        }
    
        return $this;
    }


    /**
     * Run job
     *
     * @param  Filesystem $fs
     * @return bool
     */
    public function run(Filesystem $fs, Logger $logger, Config $config): bool
    {
        $this->setOptions($config);
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
