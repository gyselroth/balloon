<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Constructor;

use Balloon\App\Notification\Api\v2;
use Balloon\App\Notification\Notifier;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Server;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     */
    public function __construct(Router $router, AttributeDecorator $decorator, Server $server, Notifier $notifier)
    {
        $router
            ->prependRoute(new Route('/api/v2/notifications(/|\z)', v2\Notifications::class))
            ->prependRoute(new Route('/api/v2/notifications/{id:#([0-9a-z]{24})#}(/|\z)', v2\Notifications::class))
            ->prependRoute(new Route('/api/v2/nodes|files|collections/subscription(/|\z)', v2\Subscription::class))
            ->prependRoute(new Route('/api/v2/nodes|files|collections/{id:#([0-9a-z]{24})#}/subscription(/|\z)', v2\Subscription::class));

        $subscription = null;

        $decorator->addDecorator('subscription', function ($node) use ($notifier, $server, &$subscription) {
            $subscription = $notifier->getSubscription($node, $server->getIdentity());
            if ($subscription === null) {
                return false;
            }

            return true;
        });

        $decorator->addDecorator('subscription_exclude_me', function ($node) use (&$subscription) {
            if ($subscription === null) {
                return false;
            }

            return $subscription['exclude_me'];
        });

        $decorator->addDecorator('subscription_recursive', function ($node) use (&$subscription) {
            if (!($node instanceof Collection)) {
                return null;
            }

            if ($subscription === null) {
                return false;
            }

            return $subscription['recursive'];
        });

        $decorator->addDecorator('subscription_throttle', function ($node) use ($notifier, &$subscription) {
            if ($subscription === null) {
                return $notifier->getThrottleTime();
            }

            return $subscription['throttle'] ?? $notifier->getThrottleTime();
        });
    }
}
