<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office;

use Balloon\User;
use Balloon\Filesystem;
use Balloon\Http\Router\Route;
use Balloon\App\AbstractApp;

class Office extends AbstractApp
{
    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->pluginmgr->registerPlugin('\Balloon\App\Office\Plugin\Office', null);
        $this->router->prependRoute((new Route('/api/v1/app/office', $this, 'start')));
        return true;
    }


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        $this->router
            ->clearRoutingTable()
            ->addRoute(new Route('/api/v1/app/office/document', 'Balloon\App\Office\Rest\v1\Document'))
            ->addRoute(new Route('/api/v1/app/office/user', 'Balloon\App\Office\Rest\v1\User'))
            ->addRoute(new Route('/api/v1/app/office/session', 'Balloon\App\Office\Rest\v1\Session'))
            ->addRoute(new Route('/api/v1/app/office/wopi/document/{id:#([0-9a-z]{24})#}', 'Balloon\App\Office\Rest\v1\Wopi\Document'))
            ->addRoute(new Route('/api/v1/app/office/wopi/document', 'Balloon\App\Office\Rest\v1\Wopi\Document'))
            ->run([$this->fs, $this->config, $this->logger]);

        return true;
    }
}
