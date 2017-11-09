<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\App;

use Balloon\App\AppInterface;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Micro\Auth;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http implements AppInterface
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
     *
     * @param Router $router
     * @param Hook   $hook
     */
    public function __construct(Router $router, Hook $hook)
    {
        $router
            ->appendRoute(new Route('/api/v1/user', 'Balloon\Api\v1\User'))
            ->appendRoute(new Route('/api/v1/user', 'Balloon\Api\v1\Admin\User'))
            ->appendRoute(new Route('/api/v1/user/{uid:#([0-9a-z]{24})#}', 'Balloon\Api\v1\User'))
            ->appendRoute(new Route('/api/v1/user/{uid:#([0-9a-z]{24})#}', 'Balloon\Api\v1\Admin\User'))
            ->appendRoute(new Route('/api/v1/resource', 'Balloon\Api\v1\Resource'))
            ->appendRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}', 'Balloon\Api\v1\File'))
            ->appendRoute(new Route('/api/v1/file', 'Balloon\Api\v1\File'))
            ->appendRoute(new Route('/api/v1/collection/{id:#([0-9a-z]{24})#}', 'Balloon\Api\v1\Collection'))
            ->appendRoute(new Route('/api/v1/collection', 'Balloon\Api\v1\Collection'))
            ->appendRoute(new Route('/api/v1/node/{id:#([0-9a-z]{24})#}', 'Balloon\Api\v1\Node'))
            ->appendRoute(new Route('/api/v1/node', 'Balloon\Api\v1\Node'))
            ->appendRoute(new Route('/api/v1$', 'Balloon\Api\v1\Api'))
            ->appendRoute(new Route('/api/v1', 'Balloon\Api\v1\Api'))
            ->appendRoute(new Route('/api$', 'Balloon\Api\v1\Api'));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if ('/index.php/api' === $_SERVER['ORIG_SCRIPT_NAME'] || '/index.php/api/v1' === $_SERVER['ORIG_SCRIPT_NAME']) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });

        return true;
    }
}
