<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\App;

use Balloon\App\Convert\Api\v1\Convert;
use Balloon\App\Convert\App;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http extends App
{
    /**
     * Constructor.
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v1/file/convert', Convert::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/convert', Convert::class));
    }
}
