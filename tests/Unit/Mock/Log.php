<?php
namespace Balloon\Testsuite\Unit\Mock;

use \Psr\Log\AbstractLogger;
use \Psr\Log\LoggerInterface;

class Log extends AbstractLogger implements LoggerInterface
{
    /**
     * Logs
     *
     * @var array
     */
    protected $store = [];


    /**
     * Log message
     *
     * @param   string $level
     * @param   string $message
     * @param   array $context
     * @return  bool
     */
    public function log($level, $message, array $context = [])
    {
        $this->store = [
            'level'     => $level,
            'message'   => $message,
            'context'   => $context
        ];

        return true;
    }
}
