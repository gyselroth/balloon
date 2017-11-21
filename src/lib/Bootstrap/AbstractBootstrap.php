<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Balloon\App;
use Balloon\Auth\Adapter\Basic\Db;
use Balloon\Converter;
use Balloon\Exception;
use Balloon\Filesystem\Storage;
use Balloon\Filesystem\Storage\Adapter\Gridfs;
use Balloon\Hook;
use Balloon\Server;
use Composer\Autoload\ClassLoader as Composer;
use ErrorException;
use Micro\Auth;
use Micro\Config;
use Micro\Container;
use Micro\Log;
use Micro\Log\Adapter\File;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Balloon\App\Notification\Notification;
use Micro\Config\Struct;

abstract class AbstractBootstrap
{
    /**
     * Config
     *
     * @var Iterable
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
     * @param Config   $config
     *
     * @return bool
     */
    public function __construct(Composer $composer, ?Config $config)
    {
        $this->config = $config;
        $this->setErrorHandler();
        $this->detectApps($composer);
        $this->container = new Container($this->config);
        $this->setErrorHandler();

        $this->container->get(LoggerInterface::class)->info('--------------------------------------------------> PROCESS', [
            'category' => get_class($this),
        ]);

        $this->container->get(LoggerInterface::class)->info('use ['.APPLICATION_ENV.'] environment', [
            'category' => get_class($this),
        ]);

        $this->container->add(get_class($composer), function () use ($composer) {
            return $composer;
        });

        $this->container->add(Client::class, function () {
            return new Client($this->getParam(Client::class, 'uri'), [], [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ],
            ]);
        });

        $this->container->add(Database::class, function () {
            return $this->get(Client::class)->balloon;
        });


        //register all app bootstraps
        $this->container->get(App::class);

        return true;
    }


    /**
     * Find apps.
     *
     * @return AbstractBootstrap
     */
    protected function detectApps(Composer $composer): AbstractBootstrap
    {
        if ($this instanceof Http) {
            $context = 'Http';
        } else {
            $context = 'Cli';
        }

        foreach (glob(APPLICATION_PATH.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $ns = str_replace('.', '\\', basename($app)).'\\';
            $composer->addPsr4($ns, $app);
            $name =  $ns.'App';

            if(!isset($this->config[App::class]['adapter'][$name])) {
                $this->config[App::class]['adapter'][$name] = new Config();
            }

            if(file_exists($app.DIRECTORY_SEPARATOR.'.container.config.php')) {
                $this->config->inject(new Struct(require_once $app.DIRECTORY_SEPARATOR.'.container.config.php'));
            }
        }

        foreach($this->config[App::class]['adapter'] as $app => $options) {
            $this->config[App::class]['adapter'][$app]['expose'] = true;
            $this->config[App::class]['adapter'][$app]['use'] = $app.'\\'.$context;

            if(!class_exists($this->config[App::class]['adapter'][$app]['use'])) {
                $this->config[App::class]['adapter'][$app]['enabled'] = '0';
            }
        }

        return $this;
    }

    /**
     * Set error handler.
     *
     * @return AbstractBootstrap
     */
    protected function setErrorHandler(): AbstractBootstrap
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            $log = $message.' in '.$file.':'.$line;

            if($this->container === null) {
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