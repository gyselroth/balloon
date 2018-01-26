<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Constructor;

use Balloon\App\Notification\Api\Latest\Notification as Api;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Balloon\Server;

class Http
{
    /**
     * Constructor.
     *
     * @param Router             $router
     * @param AttributeDecorator $decorator
     * @param Server $server
     */
    public function __construct(Router $router, AttributeDecorator $decorator, Server $server)
    {
        $router
            ->prependRoute(new Route('/api/v2/notification', Api::class))
            ->prependRoute(new Route('/api/v2/notification/{id:#([0-9a-z]{24})#}', Api::class));

        $decorator->addDecorator('subscription', function ($node) use($server) {
            $subscription = $node->getAppAttribute('Balloon\\App\\Notification', 'subscription');

            if(is_array($subscription)) {
                return isset($subscription[(string)$server->getIdentity()->getId()]);
            } else {
                return false;
            }
        });

        $decorator->addDecorator('subscription_exclude_me', function ($node) use($server) {
            $subscription = $node->getAppAttribute('Balloon\\App\\Notification', 'subscription');

            if(is_array($subscription) && isset($subscription[(string)$server->getIdentity()->getId()]['exclude_me'])) {
                return $subscription[(string)$server->getIdentity()->getId()]['exclude_me'];
            } else {
                return false;
            }
        });

        $decorator->addDecorator('subscription_recursive', function ($node) use($server) {
            if(!($node instanceof Collection)) {
                return null;
            }

            $subscription = $node->getAppAttribute('Balloon\\App\\Notification', 'subscription');

            if(is_array($subscription) && isset($subscription[(string)$server->getIdentity()->getId()]['recursive'])) {
                return $subscription[(string)$server->getIdentity()->getId()]['recursive'];
            } else {
                return false;
            }
        });
    }
}
