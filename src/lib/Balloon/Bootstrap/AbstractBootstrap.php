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

use \Micro\Config;
use \Micro\Http\Router;
use Composer\Autoload\ClassLoader as Composer;

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
     * Init bootstrap
     *
     * @param   Composer $composer
     * @param   Config $router
     * @return  void
     */
    public function __construct(Composer $composer, Config $config)
    {
        $this->composer = $composer;
        $this->config   = $config;

        $this->init();
    }


    /**
     * Init
     *
     * @return bool
     */
    abstract public function init(): bool;
}
