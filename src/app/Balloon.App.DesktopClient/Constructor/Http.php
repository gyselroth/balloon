<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\DesktopClient\Constructor;

use Balloon\App\DesktopClient\Api\v2\Download;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v2/desktop-clients', Download::class))
            ->prependRoute(new Route('/api/v2/desktop-clients/{format:#([a-z]+)#}', Download::class));
    }
}
