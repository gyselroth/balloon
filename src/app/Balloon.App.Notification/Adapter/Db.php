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

class Db implements AdapterInterface
{
    public function __construct(/*App $app,*/ LoggerInterface $logger)
    {
        //$this->app = $app;
        $this->logger = $logger;
    }

    public function notify(array $receiver, string $subject, string $body, array $context=[]): bool
    {
        foreach ($receiver as $rec) {
            /*$receive->setAppAttribute($this->app, 'notification', [
                'subject' => $subject,
                'body'    => $body,
            ]);*/
        }

        return true;
    }
}
