<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Composer\Autoload\ClassLoader as Composer;
use Micro\Container\Container;
use Noodlehaus\Config;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use ErrorException;

class ContainerBuilder
{
    /**
     * Init bootstrap.
     */
    public static function get(Composer $composer): ContainerInterface
    {
        $config = self::loadConfig();
        $config = self::detectApps($config, $composer);
        $container = new Container($config);
        self::setErrorHandler($container->get(LoggerInterface::class));

        return $container;
    }

    /**
     * Load config.
     */
    protected static function loadConfig(): Config
    {
        $configs[] = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'app.php';

        foreach (glob(constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'*.yaml') as $path) {
            clearstatcache(true, $path);
            $configs[] = $path;
        }

        return new Config($configs);
    }

    /**
     * Set error handler.
     */
    protected static function setErrorHandler(LoggerInterface $logger): void
    {
        set_error_handler(function ($severity, $message, $file, $line) use ($logger) {
            $log = $message.' in '.$file.':'.$line;

            switch ($severity) {
                case E_ERROR:
                case E_USER_ERROR:
                    $logger->error($log, [
                        'category' => self::class,
                    ]);

                break;
                case E_WARNING:
                case E_USER_WARNING:
                    $logger->warning($log, [
                        'category' => self::class,
                    ]);

                break;
                default:
                    $logger->debug($log, [
                        'category' => self::class,
                    ]);

                break;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Find apps.
     */
    protected static function detectApps(Config $master, Composer $composer): Config
    {
        $apps = [];
        $configs = [];

        foreach (glob(constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $name = basename($app);
            $ns = str_replace('.', '\\', $name).'\\';
            $composer->addPsr4($ns, $app);

            if (!file_exists($app.DIRECTORY_SEPARATOR.'app.php')) {
                continue;
            }

            $file = $app.DIRECTORY_SEPARATOR.'app.php';
            $load_master = isset($master['Apps'][$name]['enabled']) ? $master['Apps'][$name]['enabled'] : null;

            if ($load_master === false) {
                continue;
            }

            $app = new Config($file);
            $load = isset($app['Apps'][$name]['enabled']) ? $app['Apps'][$name]['enabled'] : null;

            if ($load_master === null && $load === null || $load_master === true || $load_master === null && $load === true) {
                $apps[] = $app;
            }
        }

        $new = new Config([]);
        foreach ($apps as $app) {
            $new->merge($app);
        }

        $new->merge($master);

        return $new;
    }
}
