<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Balloon\Hook;
use Balloon\Server;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\ExceptionInterface;
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
     */
    public function process()
    {
        $this->logger->info('processing incoming http ['.$_SERVER['REQUEST_METHOD'].'] request to ['.$_SERVER['REQUEST_URI'].']', [
            'category' => static::class,
        ]);

        $this->hook->run('preAuthentication', [$this->auth]);

        if ($this->auth->requireOne()) {
            $this->hook->run('postAuthentication', [$this->auth, $this->auth->getIdentity()]);

            if (
                !($this->auth->getIdentity()->getAdapter() instanceof AuthNone)) {
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
        $this->hook->run('postAuthentication', [$this->auth, null]);

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

            $response = (new Response())
                ->setCode($code)
                ->setBody([
                    'error' => 'Unauthorized',
                    'message' => 'authentication failed',
                ]);

            if (!isset($_SERVER['HTTP_AUTHORIZATION']) || substr($_SERVER['HTTP_AUTHORIZATION'], 0, 5) === 'Basic') {
                $response->setHeader('WWW-Authenticate', 'Basic realm="balloon"');
            }

            $response->send();
        }
    }

    /**
     * Set exception handler.
     */
    protected function setExceptionHandler(): self
    {
        set_exception_handler(function ($e) {
            $this->logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => static::class,
                'exception' => $e,
            ]);

            $code = 500;
            if ($e instanceof ExceptionInterface) {
                $code = $e->getStatusCode();
            }

            (new Response())
                ->setCode($code)
                ->setBody([
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                ])
                ->send();
        });

        return $this;
    }
}
