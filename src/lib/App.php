<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\App\AppInterface;
use Balloon\App\Exception;
use Composer\Autoload\ClassLoader as Composer;
use Micro\Container;
use Psr\Log\LoggerInterface;
use Micro\Container\AdapterAwareInterface;

class App implements AdapterAwareInterface
{
    /**
     * App namespaces.
     *
     * @var array
     */
    protected $namespace = [];

    /**
     * Apps.
     *
     * @var array
     */
    protected $app = [];

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Init app manager.
     *
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(LoggerInterface $logger, Hook $hook, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->hook = $hook;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return App
     */
    public function setOptions(?Iterable $config = null): App
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $name => $app) {
            $this->injectApp($name, $app);
        }

        return $this;
    }

    /**
     * Register app.
     *
     * @param string   $name
     * @param iterable $config
     *
     * @return bool
     */
    public function registerApp(Container $container, string $name, string $class, ?Iterable $config = null): bool
    {
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

        if (is_callable([$app, 'getHooks'])) {
            foreach ($app->getHooks() as $hook) {
                $this->hook->injectHook($container->get($hook));
            }
        }

        $this->logger->info('register ['.$class.'] from app ['.$name.']', [
             'category' => get_class($this),
        ]);

        $this->app[$name] = $app;

        return true;
    }

    /**
     * Start apps.
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
     * Inject app.
     *
     * @param AppInterface $app
     *
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
     * Has app namespace.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAppNamespace(string $name): bool
    {
        return isset($this->namespace[$name]);
    }

    /**
     * Has app.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasApp(string $name): bool
    {
        return isset($this->app[$name]);
    }

    /**
     * Get app.
     *
     * @param string $name
     *
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
     * Get apps.
     *
     * @param array $apps
     *
     * @return array
     */
    public function getApps(array $apps = []): array
    {
        if (empty($app)) {
            return $this->app;
        }
        $list = [];
        foreach ($apps as $name) {
            if (!$this->hasApp($name)) {
                throw new Exception('app '.$name.' is not registered');
            }
            $list[$name] = $this->app[$name];
        }

        return $list;
    }

    /**
     * Get app namespaces.
     *
     * @param array $apps
     *
     * @return array
     */
    public function getAppNamespaces(array $apps = []): array
    {
        if (empty($app)) {
            return $this->namespace;
        }
        $list = [];
        foreach ($app as $name) {
            if (!$this->hasAppNamespace($name)) {
                throw new Exception('app '.$name.' is not registered');
            }
            $list[$name] = $this->app[$name];
        }

        return $list;
    }

    /**
     * Has adapter.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return $this->hasApp($name);
    }

    /**
     * Inject adapter.
     *
     * @param string           $name
     * @param AdapterInterface $adapter
     *
     * @return AdapterInterface
     */
    public function injectAdapter(string $name, AppInterface $adapter): App
    {
        return $this->injectApp($adapter);
    }

    /**
     * Get adapter.
     *
     * @param string $name
     *
     * @return AdapterInterface
     */
    public function getAdapter(string $name): AppInterface
    {
        return $this->getApp($name);
    }

    /**
     * Get adapters.
     *
     * @param array $adapters
     *
     * @return array
     */
    public function getAdapters(array $adapters = []): array
    {
        return $this->getApps($adapters);
    }
}
