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
use \Micro\Http\Router;
use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Server;
use \Micro\Auth;
use \Micro\Config;

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
     * Server
     *
     * @var Server
     */
    protected $server;


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
        Server $server,
        Logger $logger,
        ?Router $router=null,
        ?Auth $auth=null)
    {
        $this->composer = $composer;
        $this->config   = $config;
        $this->router   = $router;
        $this->logger   = $logger;
        $this->server   = $server;
        $this->fs       = $server->getFilesystem();
        $this->auth     = $auth;

        $this->init();
    }
}
