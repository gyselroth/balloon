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
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     *
     * @param Router             $router
     * @param AttributeDecorator $decorator
     */
    public function __construct(Router $router, AttributeDecorator $decorator)
    {
        $router
            ->prependRoute(new Route('/api/v2/notification', Api::class))
            ->prependRoute(new Route('/api/v2/notification/{id:#([0-9a-z]{24})#}', Api::class));

        $decorator->addDecorator('subscription', function ($node) {
            return (bool) $node->getAppAttribute('Balloon\\App\\Notification', 'subscription');
        });
    }
}
