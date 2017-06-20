<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */


namespace Balloon\Log\Adapter;

class Blackhole extends AbstractAdapter
{
    /**
     * Log
     *
     * @param   string $level
     * @param   string $message
     * @return  bool
     */
    public function log(string $level, string $message): bool
    {
        return true;
    }
}
