<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Feedback\Constructor;

use Balloon\App\Feedback\Api\v2;
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
    public function __construct(Router $router, Hook $hook, Auth $auth)
    {
        $router->prependRoute(new Route('/api/v2/feedbacks', v2\Feedbacks::class));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if ('/index.php/api/v2/feedbacks' === $_SERVER['ORIG_SCRIPT_NAME']) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });
    }
}
