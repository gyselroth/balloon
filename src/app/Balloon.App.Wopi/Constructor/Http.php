<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Constructor;

use Balloon\App\Wopi\Api\v2;
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
            ->prependRoute(new Route('/api/v2/office/documents', v2\Documents::class))
            ->prependRoute(new Route('/api/v2/office/hosts', v2\Hosts::class))
            ->prependRoute(new Route('/api/v2/files/{id:#([0-9a-z]{24})#}/tokens', v2\Tokens::class))
            ->prependRoute(new Route('/wopi/files', v2\Wopi\Files::class))
            ->prependRoute(new Route('/wopi/files/{id:#([0-9a-z]{24})#}', v2\Wopi\Files::class))

            //TODO #407 drop support with balloon v3
            ->prependRoute(new Route('/api/v2/office/wopi/files', v2\Wopi\Files::class))
            ->prependRoute(new Route('/api/v2/office/wopi/files/{id:#([0-9a-z]{24})#}', v2\Wopi\Files::class));
    }
}
