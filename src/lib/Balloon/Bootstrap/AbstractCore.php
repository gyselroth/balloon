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

use Balloon\Http\Router;
use Balloon\Exception;
use Balloon\Config;
use Balloon\Logger;
use Balloon\Plugin;
use Balloon\Queue;
use Balloon\Filesystem;
use \Composer\Autoload\ClassLoader as Composer;
use Balloon\Http\Router\Route;
use Balloon\Http\Response;

abstract class AbstractCore extends AbstractBootstrap
{
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
     * @var Config
     */
    protected $option_log ;


    /**
     * Option: apps
     *
     * @var array
     */
    protected $option_apps = [];


    /**
     * Option: plugins
     *
     * @var Config
     */
    protected $option_plugins;


    /**
     * Init bootstrap
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->setOptions($this->config);

        $this->logger = new Logger($this->option_log);
        $this->logger->info('----------------------------------------------------------------------------> PROCESS', [
            'category' => get_class($this)
        ]);

        $this->logger->info('use ['.APPLICATION_ENV.'] environment', [
            'category' => get_class($this),
        ]);

        $this->setErrorHandler();

        $this->pluginmgr = new Plugin($this->logger);
        $this->pluginmgr->registerPlugin($this->option_plugins);

        $this->logger->info('connect to mongodb ['.$this->option_mongodb.']', [
            'category' => get_class($this),
        ]);

        $client = new \MongoDB\Client($this->option_mongodb);
        $this->db = $client->{$this->option_mongodb_db};
        
        $this->queuemgr = new Queue($this->db, $this->logger, $this->config);
        $this->fs = new Filesystem($this->db, $this->logger, $this->config, $this->queuemgr, $this->pluginmgr);

        return true;
    }


    /**
     * Set options
     *
     * @param  Config $config
     * @return AbstractCore
     */
    public function setOptions(Config $config): AbstractCore
    {
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
     * @return Core
     */
    protected function setErrorHandler()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $msg = $errstr." in ".$errfile.":".$errline;
            switch ($errno) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->logger->error($msg, [
                        'category' => get_class($this)
                    ]);
                    $code = Exception\Coding::ERROR;
                break;
            
                case E_WARNING:
                case E_USER_WARNING:
                    $this->logger->warning($msg, [
                        'category' => get_class($this)
                    ]);
                    $code = Exception\Coding::WARNING;
                break;
            
                default:
                    $this->logger->debug($msg, [
                        'category' => get_class($this)
                    ]);
                    $code = Exception\Coding::DEBUG;
                break;
            }

            throw new Exception\Coding($errstr, $code);
        });

        return $this;
    }
}
