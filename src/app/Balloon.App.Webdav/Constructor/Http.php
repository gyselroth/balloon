<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webdav\Constructor;

use Balloon\App\Webdav\LockBackend;
use Balloon\Server;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Sabre\DAV;

class Http
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Lock backend.
     *
     * @var LockBackend
     */
    protected $backend;

    /**
     * Constructor.
     */
    public function __construct(Router $router, Server $server, LockBackend $backend)
    {
        $this->server = $server;
        $this->backend = $backend;
        $router->appendRoute(new Route('/webdav', $this, 'start'));
    }

    /**
     * Start.
     */
    public function start(): bool
    {
        $root = $this->server->getFilesystem()->getRoot();

        $server = new DAV\Server($root);
        $server->setBaseUri('/webdav/');

        $plugin = new DAV\Locks\Plugin($this->backend);
        $server->addPlugin($plugin);

        $lock = new DAV\Browser\Plugin();
        $server->addPlugin($lock);

        $backend = new DAV\Auth\Backend\Apache();
        $auth = new DAV\Auth\Plugin($backend);
        $server->addPlugin($auth);

        $server->exec();

        return true;
    }
}
