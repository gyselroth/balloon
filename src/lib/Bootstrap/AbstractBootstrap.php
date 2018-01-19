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
        $configs = $this->detectApps($composer);
        $this->config = $this->loadConfig($configs);
        $this->container = new Container($this->config);
        $this->setErrorHandler();
        $this->container->get(LoggerInterface::class)->info('--------------------------------------------------> PROCESS', [
            'category' => get_class($this),
        ]);

        $this->container->add(get_class($composer), $composer);
        $this->registerAppConstructors();
    }

    /**
     * Execute app constructors.
     *
     * @return AbstractBootstrap
     */
    protected function registerAppConstructors(): self
    {
        //register all app bootstraps
        $context = $this->getContext();
        foreach ($this->config['apps'] as $app => $enabled) {
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
     * Load config.
     *
     * @param array $configs
     *
     * @return Config
     */
    protected function loadConfig(array $configs = []): Config
    {
        array_unshift($configs, __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php');
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
