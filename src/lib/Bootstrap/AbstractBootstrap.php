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
use MongoDB\Client;
use MongoDB\Database;
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
        $configs = $this->detectApps($composer);
        $this->config = $this->loadConfig($configs);

        if (isset($this->config['service'])) {
            $container = $this->config['service'];
        } else {
            $container = [];
        }

        $this->container = new Container($container);
        $this->setErrorHandler();

        $this->container->get(LoggerInterface::class)->info('--------------------------------------------------> PROCESS', [
            'category' => get_class($this),
        ]);

        $this->container->add(get_class($composer), function () use ($composer) {
            return $composer;
        });

        $container = $this->container;
        $this->container->add(Client::class, function () use ($container) {
            return new Client($container->getParam(Client::class, 'uri'), [], [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ],
            ]);
        });

        $this->container->add(Database::class, function () use ($container) {
            return $container->get(Client::class)->balloon;
        });

        //register all app bootstraps
        $this->container->get(App::class);
    }

    /**
     * Load config.
     *
     * @param array $configs
     *
     * @return Config
     */
    protected function loadConfig(array $configs = []): Config
    {
        /*if (extension_loaded('apc') && apc_exists('config')) {
            $config = apc_fetch('config');
        } else {
            $file = constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'config.xml';
            $default = require constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'.container.config.php';
            $config = new Config(new Struct($default));

            if (is_readable($file)) {
                $xml = new Xml($file, constant('BALLOON_ENV'));
                $config->inject($xml);
            }

            if (extension_loaded('apc')) {
                apc_store('config', $config);
            }
        }*/

        array_unshift($configs, __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php');
        foreach (glob(constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'*.yaml') as $path) {
            $configs[] = $path;
        }

        $config = new Config($configs);

        $apps = $config['service'];
        $context = $this->getContext();

        foreach ($apps[App::class]['adapter'] as $app => $options) {
            $options['expose'] = true;
            $options['use'] = $app.'\\'.$context;

            if (!class_exists($options['use'])) {
                $options['enabled'] = '0';
            }

            $apps[App::class]['adapter'][$options['use']] = $options;
            unset($apps[App::class]['adapter'][$app]);
        }
        $config['service'] = $apps;

        return $config;
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
     * @return array
     */
    protected function detectApps(Composer $composer): array
    {
        $configs = [];

        foreach (glob(constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $ns = str_replace('.', '\\', basename($app)).'\\';
            $composer->addPsr4($ns, $app);
            $name = $ns.'App';

            if (file_exists($app.DIRECTORY_SEPARATOR.'.container.config.php')) {
                $configs[] = $app.DIRECTORY_SEPARATOR.'.container.config.php';
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
