<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Mock;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class Log extends AbstractLogger implements LoggerInterface
{
    /**
     * Logs.
     *
     * @var array
     */
    protected $store = [];

    /**
     * Log message.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     *
     * @return bool
     */
    public function log($level, $message, array $context = [])
    {
        $this->store = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        return true;
    }
}
