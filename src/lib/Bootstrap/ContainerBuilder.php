<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
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
        $configs = self::detectApps($composer);
        $config = self::loadConfig($configs);
        $container = new Container($config);
        //$container->add(get_class($composer), $composer);

        return $container;
    }

    /**
     * Load config.
     */
    protected static function loadConfig(array $configs): Config
    {
        $global = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php';
        array_unshift($configs, $global);

        foreach (glob(constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'*.yaml') as $path) {
            clearstatcache(true, $path);
            $configs[] = $path;
        }

        return new Config($configs);
    }

    /**
     * Find apps.
     */
    protected static function detectApps(Composer $composer): array
    {
        $configs = [];

        foreach (glob(constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $name = basename($app);
            $ns = str_replace('.', '\\', $name).'\\';
            $composer->addPsr4($ns, $app);

            if (file_exists($app.DIRECTORY_SEPARATOR.'.container.config.php')) {
                $configs[] = $app.DIRECTORY_SEPARATOR.'.container.config.php';
            }
        }

        return $configs;
    }
}
