<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Composer\Autoload\ClassLoader as Composer;
use Micro\Container\Container;
use Noodlehaus\Config;
use Psr\Container\ContainerInterface;

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

        return $container;
    }

    /**
     * Load config.
     */
    protected static function loadConfig(): Config
    {
        $configs[] = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php';

        foreach (glob(constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'*.yaml') as $path) {
            clearstatcache(true, $path);
            $configs[] = $path;
        }

        return new Config($configs);
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

            if (!file_exists($app.DIRECTORY_SEPARATOR.'.container.config.php')) {
                continue;
            }

            $file = $app.DIRECTORY_SEPARATOR.'.container.config.php';
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
