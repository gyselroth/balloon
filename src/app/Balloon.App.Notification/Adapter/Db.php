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

use Balloon\Async\Mail;
use Balloon\Async;
use Psr\Log\LoggerInterface;
use Balloon\App\Notification\App;
use MongoDB\BSON\UTCDateTime;

class Db implements AdapterInterface
{
    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     * {@inheritDoc}
     */
    public function notify(array $receiver, ?User $sender, string $subject, string $body, array $context=[]): bool
    {
        foreach ($receiver as $user) {
            $this->logger->debug('send notification ['.$subject.'] to user ['.$user->getId().']', [
                'category' => get_class($this)
            ]);

            $user->setAppAttribute('Balloon\\App\\Notification', 'notification', [
                'subject'   => $subject,
                'body'      => $body,
                'from'      => $sender->getId(),
                'timestamp' => new UTCDateTime()
            ]);
        }

        return true;
    }
}
