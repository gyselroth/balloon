<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\Constructor;

use Balloon\App\Webauthn\Api\v2;
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
            ->appendRoute(new Route('/api/v2/devices', v2\Devices::class))
            ->appendRoute(new Route('/api/v2/users/{id:#([0-9a-z]{24})#}/request-challenges', v2\RequestChallenges::class))
            ->appendRoute(new Route('/api/v2/creation-challenges', v2\CreationChallenges::class));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if (preg_match('#^/index.php/api/v2/users/([0-9a-z]{24})/request-challenges#', $_SERVER['ORIG_SCRIPT_NAME'])) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });

        return true;
    }
}
