<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use \Micro\Http\Router;
use \Micro\Http\Response;
use \Balloon\Server\User;
use \Micro\Auth;
use \Micro\Auth\Adapter\None as AuthNone;
use \Balloon\Server;
use \Psr\Log\LoggerInterface;
use \Micro\Config;
use \Composer\Autoload\ClassLoader as Composer;
use \Balloon\Auth\Adapter\Basic\Db;
use \Balloon\App;
use \Balloon\Hook;

class Http extends AbstractBootstrap
{
    /**
     * Init bootstrap
     *
     * @return void
     */
    public function __construct(Composer $composer, ?Config $config=null)
    {
        parent::__construct($composer, $config);
        $this->setExceptionHandler();

        $this->container->get(LoggerInterface::class)->info('processing incoming http ['. $_SERVER['REQUEST_METHOD'].'] request to ['.$_SERVER['REQUEST_URI'].']', [
            'category' => get_class($this),
        ]);

        $auth = $this->container->get(Auth::class);
        $this->container->get(Hook::class)->run('preAuthentication', [$auth]);

        if ($auth->requireOne()) {
            if (!($auth->getIdentity()->getAdapter() instanceof AuthNone)) {
                $this->container->get(Server::class)->setIdentity($auth->getIdentity());
            }

            return $this->container->get(Router::class)->run();
        } else {
            return $this->invalidAuthentication();
        }
    }


    /**
     * Send invalid authentication response
     *
     * @return void
     */
    protected function invalidAuthentication(): void
    {
        if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] == '_logout') {
            (new Response())
                ->setCode(401)
                ->setBody('Unauthorized')
                ->send();
        } else {
            if ($_SERVER['PATH_INFO'] === '/api/auth') {
                $code = 403;
            } else {
                $code = 401;
            }

            (new Response())
                ->setHeader('WWW-Authenticate', 'Basic realm="balloon"')
                ->setCode($code)
                ->setBody('Unauthorized')
                ->send();
        }
    }


    /**
     * Set exception handler
     *
     * @return Http
     */
    protected function setExceptionHandler(): Http
    {
        set_exception_handler(function ($e) {
            $this->container->get(LoggerInterface::class)->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e
            ]);
var_dump($e);
            (new Response())
                ->setCode(500)
                ->setBody([
                    'error'   => get_class($e),
                    'message' => $e->getMessage()
                ])
                ->send();
        });

        return $this;
    }
}
