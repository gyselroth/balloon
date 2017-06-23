<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\LdapAutoShare;

use \Balloon\User;
use \Balloon\Filesystem;
use \Balloon\Http\Router\Route;
use \Balloon\App\AbstractApp;

class Init extends AbstractApp
{
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
}
