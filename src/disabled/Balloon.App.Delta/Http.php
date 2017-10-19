<?php declare(strict_types=1);
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Delta;

use \Balloon\App\Delta\Hook;
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
        return $this->server->getHook()->registerHook(Hook::class);
    }
}
