<?php declare(strict_types=1);
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\AutoCreateUser;

use \Balloon\App\AutoCreateUser\Hook;
use \Balloon\App\AbstractApp;
use \Balloon\Hook as HookManager;

class Http extends AbstractApp
{
    /**
     * Hook
     *
     * @var HookManager
     */
    protected $hook;


    /**
     * Constructor
     *
     * @param Router $router
     */
    public function __construct(HookManager $hook)
    {
        $this->hook = $hook;
    }


    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        return $this->hook->registerHook(Hook::class);
    }
}
