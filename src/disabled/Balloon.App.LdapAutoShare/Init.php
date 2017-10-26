<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\LdapAutoShare;

use Balloon\App\AbstractApp;

class Init extends AbstractApp
{
    /**
     * Init.
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->pluginmgr->registerPlugin('\Balloon\App\LdapAutoShare\Plugin', null);

        return true;
    }
}
