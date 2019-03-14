<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Constructor;

use Balloon\App\Idp\Api\v2;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Router.
     *
     * @var Router
     */
    protected $router;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Constructor.
     */
    public function __construct(Router $router, Hook $hook)
    {
        $router
            ->appendRoute(new Route('/api/v2/tokens', v2\Tokens::class));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if ('/index.php/api/v2/tokens' === $_SERVER['ORIG_SCRIPT_NAME']) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });

        return true;
    }
}
