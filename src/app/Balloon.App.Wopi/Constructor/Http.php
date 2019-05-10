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
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     */
    public function __construct(Hook $hook, Router $router)
    {
        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                $skip = [
                    '/index.php/api/v2/office/wopi/files',
                ];

                foreach ($skip as $path) {
                    if (preg_match('#^'.$path.'#', $_SERVER['ORIG_SCRIPT_NAME'])) {
                        $auth->injectAdapter(new AuthNone());

                        break;
                    }
                }
            }
        });

        $router
            ->prependRoute(new Route('/api/v2/office/documents', v2\Documents::class))
            ->prependRoute(new Route('/api/v2/office/hosts', v2\Hosts::class))
            ->prependRoute(new Route('/api/v2/office/sessions', v2\Sessions::class))
            ->prependRoute(new Route('/api/v2/office/wopi/files', v2\Wopi\Files::class))
            ->prependRoute(new Route('/api/v2/office/wopi/files/{id:#([0-9a-z]{24})#}', v2\Wopi\Files::class));
    }
}
