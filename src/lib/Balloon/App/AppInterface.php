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
use Balloon\Auth;
use Balloon\Filesystem;
use Balloon\Config;
use Balloon\Queue;
use Balloon\Plugin;

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
        Composer $composer,
        Config $config,
        Router $router,
        Logger $logger,
        Filesystem $fs,
        Auth $auth);


    /**
     * Init app
     *
     * @return bool
     */
    public function init(): bool;
}
