<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Constructor;

use Balloon\App\Elasticsearch\Api\v1;
use Balloon\App\Elasticsearch\Api\v2;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /*
     * Constructor
     *
     * @param Router
     */
    public function __construct(Router $router)
    {
        $router->prependRoute(new Route('/api/v1/(node|file|collection)/search', v1\Search::class));
        $router->prependRoute(new Route('/api/v2/(nodes|files|collections)/search(/|\z)', v2\Search::class));
    }
}
