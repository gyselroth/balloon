<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api;

use \Balloon\App\AbstractApp;
use \Micro\Http\Router;
use \Micro\Http\Router\Route;

class Http extends AbstractApp
{
    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->router->addRoute((new Route('/api', $this, 'start')));
        return true;
    }


    /**
     * Start
     *
     *  @return bool
     */
    public function start(): bool
    {
        $this->router
            ->clearRoutingTable()
            ->addRoute(new Route('/api/v1/user', 'Balloon\Api\v1\User'))
            ->addRoute(new Route('/api/v1/user', 'Balloon\Api\v1\Admin\User'))
            ->addRoute(new Route('/api/v1/user/{uid:#([0-9a-z]{24})#}', 'Balloon\Api\v1\User'))
            ->addRoute(new Route('/api/v1/user/{uid:#([0-9a-z]{24})#}', 'Balloon\Api\v1\Admin\User'))
            ->addRoute(new Route('/api/v1/resource', 'Balloon\Api\v1\Resource'))
            ->addRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}', 'Balloon\Api\v1\File'))
            ->addRoute(new Route('/api/v1/file', 'Balloon\Api\v1\File'))
            ->addRoute(new Route('/api/v1/collection/{id:#([0-9a-z]{24})#}', 'Balloon\Api\v1\Collection'))
            ->addRoute(new Route('/api/v1/collection', 'Balloon\Api\v1\Collection'))
            ->addRoute(new Route('/api/v1/node/{id:#([0-9a-z]{24})#}', 'Balloon\Api\v1\Node'))
            ->addRoute(new Route('/api/v1/node', 'Balloon\Api\v1\Node'))
            ->addRoute(new Route('/api/v1$', 'Balloon\Api\v1\Api'))
            ->addRoute(new Route('/api/v1', 'Balloon\Api\v1\Api'))
            ->addRoute(new Route('/api$', 'Balloon\Api\v1\Api'));

        return $this->router->run([$this->server, $this->logger]);
    }
}
