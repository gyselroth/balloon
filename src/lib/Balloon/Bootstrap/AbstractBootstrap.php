<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use \Balloon\Exception;
use \Micro\Config;
use \Micro\Log;
use \Balloon\Hook;
use \Balloon\Async;
use \Balloon\Filesystem;
use \Balloon\Server;
use \Composer\Autoload\ClassLoader as Composer;
use \MongoDB\Client;
use \MongoDB\Database;
use \Micro\Auth;
use \Balloon\Auth\Adapter\Basic\Db;
use \Micro\Container;
use \Micro\Log\Adapter\File;
use \ErrorException;
use \Balloon\App;
use \Balloon\Filesystem\Storage;
use \Balloon\Filesystem\Storage\Adapter\Gridfs;
use \Psr\Log\LoggerInterface;
use \Balloon\Converter;

abstract class AbstractBootstrap
{
    /**
     * Config
     *
     * @var array
     */
    protected $config = [
        Client::class => [
            'options' => [
                'uri' => 'mongodb://localhost:27017',
                'db' => 'balloon'
            ]
        ],
        LoggerInterface::class => [
            'use' => Log::class,
            'adapter' => [
                'file' => [
                    'use' => File::class,
                    'options' => [
                        'config' => [
                            'file'  => APPLICATION_PATH.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR.'out.log',
                            'level' => 10,
                            'date_format' => 'Y-d-m H:i:s',
                            'format' => '[{context.category},{level}]: {message} {context.params} {context.exception}'
                        ]
                    ]
                ]
            ]
        ],
        Storage::class => [
            'adapter' => [
                'gridfs' => [
                    'use' => Gridfs::class
                ]
            ]
        ],
        Converter::class => [
            'adapter' => Converter::DEFAULT_ADAPTER
        ],
        Hook::class => [
            'adapter' => Hook::DEFAULT_ADAPTER
        ],
        Auth::class => [
            'adapter' => [
                'basic_db' => [
                    'use' => Db::class,
                ]
            ],
        ],
        App::class => [
            'adapter' => []
        ]
    ];


    /**
     * Dependency container
     *
     * @var Container
     */
    protected $container;


    /**
     * Init bootstrap
     *
     * @param  Composer $composer
     * @param  Config $config
     * @return bool
     */
    public function __construct(Composer $composer, ?Config $config)
    {
        $this->setOptions($config);
        $this->detectApps();
        $this->setErrorHandler();
        $this->container = new Container($this->config);
        $this->container->get(LoggerInterface::class)->info('--------------------------------------------------> PROCESS', [
            'category' => get_class($this)
        ]);

        $this->container->get(LoggerInterface::class)->info('use ['.APPLICATION_ENV.'] environment', [
            'category' => get_class($this),
        ]);

        $this->container->add(get_class($composer), function() use($composer) {
            return $composer;
        });

        $this->container->add(Client::class, function(){
            return new Client($this->getParam(Client::class, 'uri'), [], [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array'
                ]
            ]);
        });

        $this->container->add(Database::class, function(){
            return $this->get(Client::class)->balloon;
        });

        $this->registerApps();

        $this->container->get(Server::class)
            ->start();

        return true;
    }


    /**
     * Register apps
     *
     * @return AbstractBootstrap
     */
    protected function registerApps(): AbstractBootstrap
    {
        $manager = $this->container->get(App::class);
        foreach($this->config[App::class]['adapter'] as $name => $config) {
            $options = null;
            if(isset($config['config'])) {
                $options = $config['config'];
            }

            $manager->registerApp($this->container, $name, $config['name'], $config['use'], $options);
        }

        return $this;
    }


    /**
     * Find apps
     *
     * @return AbstractBootstrap
     */
    protected function detectApps(): AbstractBootstrap
    {
        if($this instanceof Http) {
            $context = 'Http';
        } else {
            $context = 'Cli';
        }

        foreach (glob(APPLICATION_PATH.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            //$this->config[App::class]['adapter'][basename($app)] = [];
            $ns = str_replace('.', '\\', basename($app)).'\\';
            $class = '\\'.$ns.$context;
            $this->config[App::class]['adapter'][basename($app)]['name'] = $ns.'App';
            $this->config[App::class]['adapter'][basename($app)]['use'] = $class;
            //$this->config[App::class]['adapter'][$class] = [];
            //$this->config[App::class]['adapter'][basename($app)]['use'] = $class;
        }

        return $this;
    }


    /**
     * Set options
     *
     * @param  Config $config
     * @return AbstractBootstrap
     */
    public function setOptions(?Config $config): AbstractBootstrap
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config->children() as $option => $value) {
            /*if(!isset($value['name'])) {
                throw new Exception('invalid configuration given, objects needs service name';
            }*/

            if(!isset($this->config[$value['name']])) {
                $this->config[$value['name']] = [];
            }

            foreach($value as $type => $config) {
                switch($type) {
                    case 'class':
                        $this->config[$value['service']]['class'] = $config;
                    break;
                    case 'adapter':
                        $this->config[$value['service']]['adapter'] = $config;
                    break;
                    case 'options':
                        $this->config[$value['service']]['options'] = $config;
                    break;
                }
            }
        }

        return $this;
    }


    /**
     * Set error handler
     *
     * @return AbstractBootstrap
     */
    protected function setErrorHandler(): AbstractBootstrap
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            $log = $message." in ".$file.":".$line;
            switch ($severity) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->container->get(LoggerInterface::class)->error($log, [
                        'category' => get_class($this)
                    ]);
                break;

                case E_WARNING:
                case E_USER_WARNING:
                    $this->container->get(LoggerInterface::class)->warning($log, [
                        'category' => get_class($this)
                    ]);
                break;

                default:
                    $this->container->get(LoggerInterface::class)->debug($log, [
                        'category' => get_class($this)
                    ]);
                break;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        return $this;
    }
}
