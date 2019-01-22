<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Adapter;

use Balloon\App\Notification\MessageInterface;
use Balloon\Server\User;

interface AdapterInterface
{
    /**
     * Send notification.
     *
     * @param User $sender
     */
    public function notify(User $receiver, ?User $sender, MessageInterface $message, array $context = []): bool;
}
