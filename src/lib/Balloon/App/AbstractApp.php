<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App;

use \Composer\Autoload\ClassLoader as Composer;
use Balloon\Http\Router;
use \Psr\Log\LoggerInterface as Logger;
use Balloon\Filesystem;
use Balloon\Auth;
use Balloon\Config;
use Balloon\Queue;
use Balloon\Plugin;

abstract class AbstractApp implements AppInterface
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
     * Init bootstrap
     *
     * @param   \Composer\Autoload\ClassLoader $composer
     * @param   \Balloon\Http\Router $router
     * @return  void
     */
    public function __construct(
        Composer $composer,
        Config $config,
        Router $router,
        Logger $logger,
        Filesystem $fs,
        Auth $auth)
    {
        $this->composer = $composer;
        $this->config   = $config;
        $this->router   = $router;
        $this->logger   = $logger;
        $this->fs       = $fs;
        $this->db       = $fs->getDatabase();
        $this->pluginmgr= $fs->getPlugin();
        $this->queuemgr = $fs->getQueue();
        $this->auth     = $auth;

        $this->init();
    }
}
