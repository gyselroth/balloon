<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\App;

use Balloon\App\Preview\Api\v1\Preview;
use Balloon\App\AppInterface;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Http implements AppInterface
{
    /**
     * Constructor.
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v1/file/preview', Preview::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/preview', Preview::class));
    }
}
