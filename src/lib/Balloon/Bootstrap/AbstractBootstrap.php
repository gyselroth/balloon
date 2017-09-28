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
use \ErrorException;

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
                'file'  => APPLICATION_PATH.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR.'out.log',
                'level' => 6,
                'date_format' => 'Y-d-m H:i:s',
                'format' => '[{context.category},{level}]: {message} {context.params} {context.exception}'
            ]
        ]
    ];


    /**
     * Option: apps
     *
     * @var Iterable
     */
    protected $option_app = [
        'Balloon.App.Api'           => [],
        'Balloon.App.AutoCreateUser'=> [],
        'Balloon.App.AutoDestroy'   => [],
        'Balloon.App.CleanTemp'     => [],
        'Balloon.App.CleanTrash'    => [],
        'Balloon.App.Delta'         => [],
        'Balloon.App.Notification'  => [],
        'Balloon.App.Convert'       => [],
        'Balloon.App.Preview'       => [],
        'Balloon.App.Sharelink'     => [],
        'Balloon.App.Webdav'        => [],
        'Balloon.App.Elasticsearch' => [],
    ];


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

        $client = new Client($this->option_mongodb, [], [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array'
            ]
        ]);

        $this->db = $client->{$this->option_mongodb_db};
        $this->async = new Async($this->db, $this->logger);
        $this->server = new Server($this->db, $this->logger, $this->async, $this->hook);

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
        if ($config === null) {
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
                case 'app':
                    foreach ($value as $app => $options) {
                        $this->option_app[$app] = $options;
                    }
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
        set_error_handler(function ($severity, $message, $file, $line) {
            $log = $message." in ".$file.":".$line;
            switch ($severity) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->logger->error($log, [
                        'category' => get_class($this)
                    ]);
                break;

                case E_WARNING:
                case E_USER_WARNING:
                    $this->logger->warning($log, [
                        'category' => get_class($this)
                    ]);
                break;

                default:
                    $this->logger->debug($log, [
                        'category' => get_class($this)
                    ]);
                break;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        return $this;
    }
}
