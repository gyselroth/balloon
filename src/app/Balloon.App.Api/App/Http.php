<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\App;

use Balloon\App\Api\Latest;
use Balloon\App\Api\v1;
use Balloon\App\AppInterface;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
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
            ->appendRoute(new Route('/api/v2/user', Latest\User::class))
            ->appendRoute(new Route('/api/v2/user/{uid:#([0-9a-z]{24})#}', Latest\User::class))
            ->appendRoute(new Route('/api/v2/resource', Latest\Resource::class))
            ->appendRoute(new Route('/api/v2/file/{id:#([0-9a-z]{24})#}', Latest\File::class))
            ->appendRoute(new Route('/api/v2/file', Latest\File::class))
            ->appendRoute(new Route('/api/v2/collection/{id:#([0-9a-z]{24})#}', Latest\Collection::class))
            ->appendRoute(new Route('/api/v2/collection', Latest\Collection::class))
            ->appendRoute(new Route('/api/v2/node/{id:#([0-9a-z]{24})#}', Latest\Node::class))
            ->appendRoute(new Route('/api/v2/node', Latest\Node::class))
            ->appendRoute(new Route('/api/v2$', Latest\Api::class))
            ->appendRoute(new Route('/api/v2', Latest\Api::class))
            ->appendRoute(new Route('/api$', Latest\Api::class))
            ->appendRoute(new Route('/api/v1/user', v1\User::class))
            ->appendRoute(new Route('/api/v1/user/{uid:#([0-9a-z]{24})#}', v1\User::class))
            ->appendRoute(new Route('/api/v1/resource', v1\Resource::class))
            ->appendRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}', v1\File::class))
            ->appendRoute(new Route('/api/v1/file', v1\File::class))
            ->appendRoute(new Route('/api/v1/collection/{id:#([0-9a-z]{24})#}', v1\Collection::class))
            ->appendRoute(new Route('/api/v1/collection', v1\Collection::class))
            ->appendRoute(new Route('/api/v1/node/{id:#([0-9a-z]{24})#}', v1\Node::class))
            ->appendRoute(new Route('/api/v1/node', v1\Node::class))
            ->appendRoute(new Route('/api/v1$', v1\Api::class))
            ->appendRoute(new Route('/api/v1', v1\Api::class));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if ('/index.php/api' === $_SERVER['ORIG_SCRIPT_NAME'] || '/index.php/api/v1' === $_SERVER['ORIG_SCRIPT_NAME'] ||
                  '/index.php/api/v2' === $_SERVER['ORIG_SCRIPT_NAME']) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });

        return true;
    }
}
