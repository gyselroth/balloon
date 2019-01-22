<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Balloon\Hook;
use Balloon\Server;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\Response;
use Micro\Http\Router;
use MongoDB\BSON\Binary;
use Psr\Log\LoggerInterface;

class Http extends AbstractBootstrap
{
    /**
     * Auth.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Router.
     *
     * @var Router
     */
    protected $router;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Http.
     */
    public function __construct(LoggerInterface $logger, Auth $auth, Hook $hook, Router $router, Server $server)
    {
        $this->setExceptionHandler();
        $this->setErrorHandler();
        $this->logger = $logger;
        $this->auth = $auth;
        $this->hook = $hook;
        $this->router = $router;
        $this->server = $server;
    }

    /**
     * Process.
     *
     * @return Http
     */
    public function process()
    {
        $this->logger->info('processing incoming http ['.$_SERVER['REQUEST_METHOD'].'] request to ['.$_SERVER['REQUEST_URI'].']', [
            'category' => get_class($this),
        ]);

        $this->hook->run('preAuthentication', [$this->auth]);

        if ($this->auth->requireOne()) {
            if (!($this->auth->getIdentity()->getAdapter() instanceof AuthNone)) {
                $this->auth->getIdentity()->getAttributeMap()->addMapper('binary', function ($value) {
                    return new Binary($value, Binary::TYPE_GENERIC);
                });

                $this->server->setIdentity($this->auth->getIdentity());
            }

            $this->router->run();
        } else {
            $this->invalidAuthentication();
        }

        return $this;
    }

    /**
     * Send invalid authentication response.
     */
    protected function invalidAuthentication(): void
    {
        if (isset($_SERVER['PHP_AUTH_USER']) && '_logout' === $_SERVER['PHP_AUTH_USER']) {
            (new Response())
                ->setCode(401)
                ->setBody([
                    'error' => 'Unauthorized',
                    'message' => 'authentication failed',
                ])
                ->send();
        } else {
            if ('/api/auth' === $_SERVER['PATH_INFO']) {
                $code = 403;
            } else {
                $code = 401;
            }

            (new Response())
                ->setHeader('WWW-Authenticate', 'Basic realm="balloon"')
                ->setCode($code)
                ->setBody([
                    'error' => 'Unauthorized',
                    'message' => 'authentication failed',
                ])
                ->send();
        }
    }

    /**
     * Set exception handler.
     *
     * @return Http
     */
    protected function setExceptionHandler(): self
    {
        set_exception_handler(function ($e) {
            $this->logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            (new Response())
                ->setCode(500)
                ->setBody([
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                ])
                ->send();
        });

        return $this;
    }
}
