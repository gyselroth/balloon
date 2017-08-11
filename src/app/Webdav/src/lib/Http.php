<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webdav;

use \Balloon\Exception;
use \Balloon\App;
use \Balloon\Filesystem;
use \Balloon\Auth;
use \Balloon\User;
use \Balloon\App\AbstractApp;
use \Sabre\DAV;
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
        $this->router->addRoute((new Route('/webdav', $this, 'start')));
        return true;
    }


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        //Init user directory
        $root = $this->fs->getRoot();

        //Start server
        $server = new DAV\Server($root);
        $server->setBaseUri('/webdav/');

        //The lock manager is reponsible for making sure users don't overwrite each others changes. Change 'data' to a different
        //directory, if you're storing your data somewhere else.
        $lockBackend = new DAV\Locks\Backend\File('/tmp/locks');
        $lockPlugin = new DAV\Locks\Plugin($lockBackend);
        $server->addPlugin($lockPlugin);

        //Browser plugin
        $plugin = new DAV\Browser\Plugin();
        $server->addPlugin($plugin);

        //Authentication is webserver work
        $authBackend = new DAV\Auth\Backend\Apache();
        $authPlugin = new DAV\Auth\Plugin($authBackend, 'SabreDAV');
        $server->addPlugin($authPlugin);

        //All we need to do now, is to fire up the server
        $server->exec();
        return true;
    }
}
