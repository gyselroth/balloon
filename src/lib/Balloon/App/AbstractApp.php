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
use \Psr\Log\LoggerInterface;
use \Balloon\Server;
use \Micro\Auth;
use \Balloon\Filesystem\Node\NodeInterface;

abstract class AbstractApp implements AppInterface
{
    /**
     * Router
     *
     * @var Router
     */
    protected $router;


    /**
     * LoggerInterface
     *
     * @var LoggerInterface
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
        Server $server,
        LoggerInterface $logger,
        ?Iterable $config=null,
        ?Router $router=null,
        ?Auth $auth=null
    ) {
        $this->config   = $config;
        $this->router   = $router;
        $this->logger   = $logger;
        $this->server   = $server;
        $this->fs       = $server->getFilesystem();
        $this->auth     = $auth;

        $this->setOptions($config);
        $this->init();
    }


    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        return true;
    }


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        return true;
    }


    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        $class = str_replace('\\', '_', get_class($this));
        return substr($class, 0, strrpos($class, '_'));
    }


    /**
     * Get attributes
     *
     * @param  NodeInterface $node
     * @param  array $attributes
     * @return array
     */
    public function getAttributes(NodeInterface $node, array $attributes=[]): array
    {
        return [];
    }


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AppInterface
     */
    public function setOptions(?Iterable $config=null): AppInterface
    {
        return $this;
    }
}
