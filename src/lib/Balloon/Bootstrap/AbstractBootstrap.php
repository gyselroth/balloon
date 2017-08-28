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
use \Micro\Log\Adapter\File;

abstract class AbstractBootstrap
{
    /**
     * Config
     *
     * @var Config
     */
    protected $config;


    /**
     * Composer
     *
     * @var Composer
     */
    protected $composer;


    /**
     * Router
     *
     * @var Router
     */
    protected $router;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Database
     *
     * @var Database
     */
    protected $db;


    /**
     * Queue
     *
     * @var Queue
     */
    protected $queuemgr;


    /**
     * Plugin
     *
     * @var Plugin
     */
    protected $pluginmgr;

    
    /**
     * Filesystem
     *
     * @var Filesystem
     */
    protected $fs;

    
    /**
     * User
     *
     * @var User
     */
    protected $user;


    /**
     * Option: mongodb
     *
     * @var string
     */
    protected $option_mongodb = 'mongodb://localhost:27017';

    
    /**
     * Option: mongodb database
     *
     * @var string
     */
    protected $option_mongodb_db = 'balloon';


    /**
     * Option: log
     *
     * @var Iterable
     */
    protected $option_log = [
        'file' => [
            'class' => File::class,
            'config' => [
                'file'  => APPLICATION_PATH.DIRECTORY_SEPARATATOR.'log'.DIRECTORY_SEPARATOR.'out.log',
                'level' => 3
            ]
        ]
    ];


    /**
     * Option: apps
     *
     * @var Iterable
     */
    protected $option_apps = [];


    /**
     * Init bootstrap
     *
     * @param  Composer $composer
     * @param  Config $config
     * @return bool
     */
    public function __construct(Composer $composer, ?Config $config)
    {
        $this->composer = $composer;
        $this->config   = $config;

        $this->setOptions($this->config);

        $this->logger = new Log($this->option_log);
        $this->logger->info('----------------------------------------------------------------------------> PROCESS', [
            'category' => get_class($this)
        ]);

        $this->logger->info('use ['.APPLICATION_ENV.'] environment', [
            'category' => get_class($this),
        ]);

        $this->setErrorHandler();

        $this->hook = new Hook($this->logger);
        $this->logger->info('connect to mongodb ['.$this->option_mongodb.']', [
            'category' => get_class($this),
        ]);

        $client = new Client($this->option_mongodb);
        $this->db = $client->{$this->option_mongodb_db};
       
        $this->async = new Async($this->db, $this->logger, $this->config);
        $this->server = new Server($this->db, $this->logger, $this->async, $this->hook);
        $this->fs = new Filesystem($this->server, $this->logger);
 
        return true;
    }


    /**
     * Set options
     *
     * @param  Config $config
     * @return AbstractBootstrap
     */
    public function setOptions(?Config $config): AbstractBootstrap
    {
        if($config === null) {
            return $this;
        }

        foreach ($config->children() as $option => $value) {
            switch ($option) {
                case 'mongodb':
                    if (isset($value['server'])) {
                        $this->option_mongodb = $value->server;
                    }
                    if (isset($value['db'])) {
                        $this->option_mongodb_db = $value->db;
                    }
                    break;
                case 'apps':
                    $this->option_apps = $value;
                    break;
                case 'plugins':
                    $this->option_plugins = $value;
                    break;
                case 'log':
                    $this->option_log = $value;
                    break;
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
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $msg = $errstr." in ".$errfile.":".$errline;
            switch ($errno) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->logger->error($msg, [
                        'category' => get_class($this)
                    ]);
                    $code = Exception\Internal::ERROR;
                break;
            
                case E_WARNING:
                case E_USER_WARNING:
                    $this->logger->warning($msg, [
                        'category' => get_class($this)
                    ]);
                    $code = Exception\Internal::WARNING;
                break;
            
                default:
                    $this->logger->debug($msg, [
                        'category' => get_class($this)
                    ]);
                    $code = Exception\Internal::DEBUG;
                break;
            }

            throw new Exception\Internal($errstr, $code);
        });

        return $this;
    }
}
