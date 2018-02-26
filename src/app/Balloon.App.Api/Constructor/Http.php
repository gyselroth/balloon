<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\Constructor;

use Balloon\App\Api\v1;
use Balloon\App\Api\v2;
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
     *
     * @param Router $router
     * @param Hook   $hook
     */
    public function __construct(Router $router, Hook $hook)
    {
        $router
            ->appendRoute(new Route('/api/v2/users/{id:#([0-9a-z]{24})#}(/|\z)', v2\Users::class))
            ->appendRoute(new Route('/api/v2/users(/|\z)', v2\Users::class))
            ->appendRoute(new Route('/api/v2/groups/{id:#([0-9a-z]{24})#}(/|\z)', v2\Groups::class))
            ->appendRoute(new Route('/api/v2/groups(/|\z)', v2\Groups::class))
            ->appendRoute(new Route('/api/v2/files/{id:#([0-9a-z]{24})#}(/|\z)', v2\Files::class))
            ->appendRoute(new Route('/api/v2/files(/|\z)', v2\Files::class))
            ->appendRoute(new Route('/api/v2/collections/{id:#([0-9a-z]{24})#}(/|\z)', v2\Collections::class))
            ->appendRoute(new Route('/api/v2/collections(/|\z)', v2\Collections::class))
            ->appendRoute(new Route('/api/v2/nodes/{id:#([0-9a-z]{24})#}(/|\z)', v2\Nodes::class))
            ->appendRoute(new Route('/api/v2/nodes(/|\z)', v2\Nodes::class))
            ->appendRoute(new Route('/api/v2$', v2\Api::class))
            ->appendRoute(new Route('/api/v2', v2\Api::class))
            ->appendRoute(new Route('/api$', v2\Api::class))
            ->appendRoute(new Route('/api/v1/user/{uid:#([0-9a-z]{24})#}', v1\User::class))
            ->appendRoute(new Route('/api/v1/user', v1\User::class))
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
