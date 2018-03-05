<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Balloon\App;
use Composer\Autoload\ClassLoader as Composer;
use ErrorException;
use Micro\Container\Container;
use Noodlehaus\Config;
use Psr\Log\LoggerInterface;

abstract class AbstractBootstrap
{
    /**
     * Config.
     *
     * @var iterable
     */
    protected $config;

    /**
     * Dependency container.
     *
     * @var Container
     */
    protected $container;

    /**
     * Init bootstrap.
     *
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->setErrorHandler();

        $apps = $this->detectApps($composer);
        $config = $this->loadConfig();
        $this->config = $this->loadAppConfigs($apps, $config);
        $this->container = new Container($this->config);

        $this->container->get(LoggerInterface::class)->info('--------------------------------------------------> PROCESS', [
            'category' => get_class($this),
        ]);

        $this->container->add(get_class($composer), $composer);
        $this->registerAppConstructors(array_keys($apps));
    }

    /**
     * Execute app constructors.
     *
     * @return AbstractBootstrap
     */
    protected function registerAppConstructors(array $apps): self
    {
        $context = $this->getContext();
        $app_config = (array) $this->config['apps'];

        foreach ($apps as $app) {
            if (isset($app_config[$app])) {
                $enabled = $app_config[$app];
            } else {
                $enabled = true;
            }

            $class = str_replace('.', '\\', $app).'\\Constructor\\'.$context;
            if ($enabled === true && class_exists($class)) {
                $this->container->get(LoggerInterface::class)->debug('found and execute app constructor ['.$class.']', [
                    'category' => get_class($this),
                ]);

                $this->container->get($class);
            } elseif ($enabled === false) {
                $this->container->get(LoggerInterface::class)->debug('skip disabled app constructor ['.$class.']', [
                    'category' => get_class($this),
                ]);
            }
        }

        return $this;
    }

    /**
     * Merge configurations.
     *
     * @param array  $apps
     * @param Config $config
     *
     * @return Config
     */
    protected function loadAppConfigs(array $apps, Config $config)
    {
        $root = new Config(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php');
        $app_config = (array) $config['apps'];
        $configs = [];

        foreach ($apps as $name => $config_path) {
            if (isset($app_config[$name]) && $app_config[$name] === false) {
                continue;
            }

            if ($config_path !== null) {
                $configs[] = $config_path;
            }
        }

        $apps = new Config($configs);

        return $root->merge($apps)->merge($config);
    }

    /**
     * Load config.
     *
     * @return Config
     */
    protected function loadConfig(): Config
    {
        $configs = [];
        foreach (glob(constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'*.yaml') as $path) {
            $configs[] = $path;
        }

        return new Config($configs);
    }

    /**
     * Get context.
     *
     * @return string
     */
    protected function getContext(): string
    {
        if ($this instanceof Http) {
            return 'Http';
        }

        return 'Cli';
    }

    /**
     * Find apps.
     *
     * @param Composer $composer
     *
     * @return array
     */
    protected function detectApps(Composer $composer): array
    {
        $configs = [];

        foreach (glob(constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $name = basename($app);
            $ns = str_replace('.', '\\', $name).'\\';
            $composer->addPsr4($ns, $app);

            if (file_exists($app.DIRECTORY_SEPARATOR.'.container.config.php')) {
                $configs[$name] = $app.DIRECTORY_SEPARATOR.'.container.config.php';
            } else {
                $configs[$name] = null;
            }
        }

        return $configs;
    }

    /**
     * Set error handler.
     *
     * @return AbstractBootstrap
     */
    protected function setErrorHandler(): self
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            $log = $message.' in '.$file.':'.$line;

            if (null === $this->container) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }

            switch ($severity) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->container->get(LoggerInterface::class)->error($log, [
                        'category' => get_class($this),
                    ]);

                break;
                case E_WARNING:
                case E_USER_WARNING:
                    $this->container->get(LoggerInterface::class)->warning($log, [
                        'category' => get_class($this),
                    ]);

                break;
                default:
                    $this->container->get(LoggerInterface::class)->debug($log, [
                        'category' => get_class($this),
                    ]);

                break;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        return $this;
    }
}
