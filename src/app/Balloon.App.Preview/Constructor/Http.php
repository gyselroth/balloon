<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Constructor;

use Balloon\App\Preview\Api\Latest\Preview;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Balloon\Filesystem\Node\FileInterface;

class Http
{
    /**
     * Constructor.
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v(1|2)/file/preview', Preview::class))
            ->prependRoute(new Route('/api/v(1|2)/file/{id:#([0-9a-z]{24})#}/preview', Preview::class));
    }
}
