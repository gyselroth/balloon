<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\App;

use Balloon\App\AppInterface;
use Balloon\App\Elasticsearch\Api\v1\Search as Api;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http implements AppInterface
{
    /*
     * Constructor
     *
     * @param Router
     */
    public function __construct(Router $router)
    {
        $router->prependRoute(new Route('/api/v1/node/search', Api::class));
    }
}
