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

use \Balloon\Http\Router;
use \Micro\Http\Response;
use \Balloon\Server\User;
use \Micro\Auth;
use \Micro\Auth\Adapter\None as AuthNone;
use \Micro\Config;
use \Composer\Autoload\ClassLoader as Composer;
use \Balloon\Auth\Adapter\Basic\Db;
use \Balloon\App;

class Http extends AbstractBootstrap
{
    /**
     * option: auth
     *
     * @var Iterable
     */
    protected $option_auth = [
        'basic_db' => [
            'class' => Db::class,
        ]
    ];


    /**
     * Init bootstrap
     *
     * @return void
     */
    public function __construct(Composer $composer, ?Config $config=null)
    {
        parent::__construct($composer, $config);
        $this->setExceptionHandler();

        $this->logger->info('processing incoming http ['. $_SERVER['REQUEST_METHOD'].'] request to ['.$_SERVER['REQUEST_URI'].']', [
            'category' => get_class($this),
        ]);

        $this->router = new Router($this->logger, $_SERVER);
        $this->auth = new Auth($this->logger, $this->option_auth);

        if ($this->auth->hasAdapter('basic_db')) {
            $this->auth->getAdapter('basic_db')->setOptions(['mongodb' => $this->db]);
        }

        $this->app = new App(App::CONTEXT_HTTP, $this->composer, $this->server, $this->logger, $this->option_app, $this->router, $this->auth);
        $this->app->init();

        $this->hook->run('preAuthentication', [$this->auth]);

        if ($this->auth->requireOne()) {
            if (!($this->auth->getIdentity()->getAdapter() instanceof AuthNone)) {
                $this->server->setIdentity($this->auth->getIdentity());
            }

            return $this->router->run([$this->server, $this->logger]);
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
     * Set options
     *
     * @param  Config $config
     * @return AbstractBootstrap
     */
    public function setOptions(?Config $config): AbstractBootstrap
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config->children() as $option => $value) {
            switch ($option) {
                case 'auth':
                    $this->option_auth = $value;
                break;
            }
        }

        return parent::setOptions($config);
    }


    /**
     * Set exception handler
     *
     * @return Http
     */
    protected function setExceptionHandler(): Http
    {
        set_exception_handler(function ($e) {
            $this->logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e
            ]);

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
