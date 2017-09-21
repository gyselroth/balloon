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
use \Micro\Auth;
use \Balloon\Server;
use \Balloon\Filesystem\Node\NodeInterface;

interface AppInterface
{
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
        ?Iterable $config,
        ?Router $router=null,
        ?Auth $auth=null
    );


    /**
     * Init app
     *
     * @return bool
     */
    public function init(): bool;


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool;


    /**
     * Get attributes
     *
     * @param  NodeInterface $node
     * @param  array $attributes
     * @return array
     */
    public function getAttributes(NodeInterface $node, array $attributes=[]): array;


    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string;
}
