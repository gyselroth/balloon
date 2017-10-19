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
use \Psr\Log\LoggerInterface;
use \Composer\Autoload\ClassLoader as Composer;
use \Micro\Container;

class App
{
    /**
     * App namespaces
     *
     * @var array
     */
    protected $namespace = [];


    /**
     * Apps
     *
     * @var array
     */
    protected $app = [];


    /**
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Init app manager
     *
     * @param   Composer $composer
     * @param   LoggerInterface $logger
     * @param   Iterable $config
     */
    public function __construct(Composer $composer, LoggerInterface $logger, ?Iterable $config=null)
    {
        $this->composer = $composer;
        $this->logger   = $logger;
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
            $this->injectApp($name, $app);
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
    public function registerApp(Container $container, string $name, string $class, ?Iterable $config=null): bool
    {
        $ns = str_replace('.', '\\', $name).'\\';
        //$class = '\\'.$ns.$this->context;
        $this->composer->addPsr4($ns, APPLICATION_PATH."/src/app/$name");
        $this->namespace[$name] = $ns;

        if (!class_exists($class)) {
            $this->logger->debug('skip non-existent initialize class ['.$class.'] from app ['.$name.']', [
                 'category' => get_class($this),
            ]);

            return false;
        }

        if ($this->hasApp($name)) {
            throw new Exception('app '.$name.' is already registered');
        }

        $app = $container->get($name);

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
     * Start apps
     *
     * @return bool
     */
    public function start(): bool
    {
        foreach ($this->app as $app) {
            $app->init();
        }

        return true;
    }


    /**
     * Inject app
     *
     * @param  AppInterface $app
     * @return App
     */
    public function injectApp(AppInterface $app): App
    {
        $name = str_replace('_', '.', $app->getName());
        if ($this->hasApp($name)) {
            throw new Exception('app '.$name.' is already registered');
        }

        $this->namespace[$name] = join('', array_slice(explode('\\', get_class($app)), -1));
        $this->app[$name] = $app;
        return $this;
    }


    /**
     * Has app namespace
     *
     * @param  string $name
     * @return bool
     */
    public function hasAppNamespace(string $name): bool
    {
        return isset($this->namespace[$name]);
    }


    /**
     * Has app
     *
     * @param  string $name
     * @return bool
     */
    public function hasApp(string $name): bool
    {
        return isset($this->app[$name]);
    }


    /**
     * Get app
     *
     * @param  string $name
     * @return AppInterface
     */
    public function getApp(string $name): AppInterface
    {
        if (!$this->hasApp($name)) {
            throw new Exception('app '.$name.' is not registered');
        }

        return $this->app[$name];
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
            foreach ($apps as $name) {
                if (!$this->hasApp($name)) {
                    throw new Exception('app '.$name.' is not registered');
                }
                $list[$name] = $this->app[$name];
            }

            return $list;
        }
    }


    /**
     * Get app namespaces
     *
     * @param  array $apps
     * @return array
     */
    public function getAppNamespaces(array $apps = []): array
    {
        if (empty($app)) {
            return $this->namespace;
        } else {
            $list = [];
            foreach ($app as $name) {
                if (!$this->hasAppNamespace($name)) {
                    throw new Exception('app '.$name.' is not registered');
                }
                $list[$name] = $this->app[$name];
            }

            return $list;
        }
    }


    /**
     * Has adapter
     *
     * @param  string $name
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return $this->hasApp($name);
    }


    /**
     * Inject adapter
     *
     * @param  string $name
     * @param  AdapterInterface $adapter
     * @return AdapterInterface
     */
    public function injectAdapter(string $name, AppInterface $adapter): App
    {
        return $this->injectApp($name, $adapter);
    }


    /**
     * Get adapter
     *
     * @param  string $name
     * @return AdapterInterface
     */
    public function getAdapter(string $name): AppInterface
    {
        return $this->getApp($name);
    }


    /**
     * Get adapters
     *
     * @param  array $adapters
     * @return array
     */
    public function getAdapters(array $adapters = []): array
    {
        return $this->getApps($adapters);
    }
}
