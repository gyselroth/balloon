<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \Balloon\App\Exception;
use \Balloon\App\AppInterface;
use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Http\Router;
use \Micro\Http\Response;
use \Balloon\Server\User;
use \Micro\Auth;
use \Micro\Auth\Adapter\None as AuthNone;
use \Composer\Autoload\ClassLoader as Composer;


class App
{
    /**
     * Context: http  
     */
    const CONTEXT_HTTP = 'Http';

    
    /**
     * Context: cli  
     */
    const CONTEXT_CLI = 'Cli';

    
    /**
     * Apps
     *
     * @var array
     */
    protected $app = [];


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Init app manager
     *
     * @param   Logger $logger
     * @return  void
     */
   public function __construct(string $context=self::CONTEXT_HTTP, Composer $composer, Server $server, Logger $logger, ?Iterable $config=null, ?Router $router=null, Auth $auth=null)
    {
        $this->context  = $context;
        $this->composer = $composer;
        $this->server   = $server;
        $this->router   = $router;
        $this->auth     = $auth;
        $this->logger   = $logger;

        $this->server->setApp($this);
        $this->setOptions($config);
    }


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return App
     */
    public function setOptions(?Iterable $config = null): App
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $name => $app) {
            if (!isset($app['enabled']) || $app['enabled'] === '1') {
                if (isset($app['config'])) {
                    $config = $app['config'];
                } else {
                    $config = null;
                }
                
                $this->registerApp($name, $config);
            } else {
                $this->logger->debug("skip disabled app [".$name."]", [
                    'category' => get_class($this)
                ]);
            }
        }
    
        return $this;
    }


    /**
     * Register app
     *
     * @param   string $name
     * @param   Iterable $config
     * @return  bool
     */
    public function registerApp(string $name, ?Iterable $config=null): bool
    {
        $ns = str_replace('.', '\\', $name).'\\';
        $class = '\\'.$ns.$this->context;
        $this->composer->addPsr4($ns, APPLICATION_PATH."/src/app/$name/src/lib");
        
        if (!class_exists($class)) {
            $this->logger->debug('skip non-existent class ['.$class.'] from app ['.$name.']', [
                 'category' => get_class($this),
            ]);
            return false;
        }
            
        $app = new $class($this->server, $this->logger, $config, $this->router, $this->auth);
        if ($this->hasApp($name)) {
           throw new Exception('app '.$name.' is already registered');
        }
            
        if (!($app instanceof AppInterface)) {
           throw new Exception('app class '.$class.' does not implement AppInterface');
        }

        $this->logger->info('register ['.$class.'] from app ['.$name.']', [
             'category' => get_class($this),
        ]);

        $this->app[$name] = $app;

        return true;
    }


    /**
     * Inject app
     *
     * @param  AppInterface $app
     * @return bool 
     */
    public function injectApp(AppInterface $app)
    {
        $name = str_replace('_', '.', $app->getName());
        if ($this->hasApp($name)) {
           throw new Exception('app '.$name.' is already registered');
        }
           
        $this->app[$name] = $app;
        return true;
    }


    /**
     * Has app
     *
     * @param  string $class
     * @return bool
     */
    public function hasApp(string $class): bool
    {
        return isset($this->app[$class]);
    }


    /**
     * Get app
     *      
     * @param  string $class
     * @return AppInterface
     */
    public function getApp(string $class): AppInterface
    {
        if (!$this->hasApp($class)) {
            throw new Exception('auth app '.$class.' is not registered');
        }

        return $this->app[$class];
    }


    /**
     * Get apps
     *      
     * @param  array $apps
     * @return array
     */
    public function getApps(array $apps = []): array
    {
        if (empty($app)) {
            return $this->app;
        } else {
            $list = [];
            foreach ($app as $class) {
                if (!$this->hasApp($class)) {
                    throw new Exception('auth app '.$class.' is not registered');
                }
                $list[$class] = $this->app[$class];
            }

            return $list;
        }
    }
}
