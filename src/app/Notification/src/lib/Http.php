<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use \Balloon\Filesystem;
use \Balloon\Http\Router\Route;
use \Balloon\App\AbstractApp;

class Http extends AbstractApp
{
    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->server->getHook()->registerHook('\Balloon\App\Notification\Hook', null);
        return true;
    }
}
