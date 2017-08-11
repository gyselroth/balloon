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
use \Balloon\App\AppInterface;
use \Micro\Config;
use \Composer\Autoload\ClassLoader as Composer;

class Http extends AbstractBootstrap
{
    /**
     * option: auth
     *
     * @var Config
     */
    protected $option_auth;


    /**
     * Init bootstrap
     *
     * @return void
     */
    public function __construct(Composer $composer, Config $config)
    {
        parent::__construct($composer, $config);
        $this->setExceptionHandler();

        $this->logger->info('processing incoming http ['. $_SERVER['REQUEST_METHOD'].'] request to ['.$_SERVER['REQUEST_URI'].']', [
            'category' => get_class($this),
        ]);

        $this->router = new Router($_SERVER, $this->logger);
        $this->auth = new Auth($this->option_auth, $this->logger);

        $this->loadApps();

        if($this->auth->requireOne())  {
            $this->server->setIdentity($this->auth->getIdentity());

            if (!($this->auth->getIdentity()->getAdapter() instanceof AuthNone)) {
                $this->server->setIdentity($this->auth->getIdentity());
            }

            return $this->router->run();
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
            if ($_SERVER['REQUEST_URI'] == '/api/auth') {
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
    public function setOptions(Config $config): AbstractBootstrap
    {
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
     * Load apps
     *
     * @return bool
     */
    protected function loadApps(): bool
    {
        foreach ($this->option_apps as $app) {
            $ns = ltrim((string)$app->class, '\\');
            $name = substr($ns, strrpos($ns, '\\') + 1);
            $this->composer->addPsr4($ns.'\\', APPLICATION_PATH."/src/app/$name/src/lib");
            $class = $ns.'\\Http';

            if (isset($app['enabled']) && $app['enabled'] != "1") {
                $this->logger->debug('skip disabled app ['.$class.']', [
                   'category' => get_class($this)
                ]);
                continue;
            }
            
            if(class_exists($class)) {
                $this->logger->info('inject app ['.$class.']', [
                    'category' => get_class($this)
                ]);

                $app = new $class($this->composer, $app->config, $this->server, $this->logger, $this->router, $this->auth);

                if (!($app instanceof AppInterface)) {
                    throw new Exception('app '.$class.' is required to implement AppInterface');
                }
            } else {
                $this->logger->debug('app ['.$class.'] does not exists, skip it', [
                    'category' => get_class($this)
                ]);
            }
        }

        return true;
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

            (new Response)
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
