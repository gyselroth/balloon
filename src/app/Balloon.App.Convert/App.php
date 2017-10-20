<?php declare(strict_types=1);
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use \Balloon\App\AbstractApp;
use \Balloon\App\AppInterface;
use \Balloon\Converter;

class App extends AbstractApp
{
    /**
     * Get hooks
     *
     * @return array
     */
    public function getHooks(): array
    {
        return [
            Hook::class
        ];
    }
}
