<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Cli\Console;

use Balloon\Hook;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;

class Jobs
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Constructor.
     */
    public function __construct(Hook $hook, Async $async, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->async = $async;
        $this->hook = $hook;
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->async = $async;
    }

    /*
     * Get operands
     *
     * @return array
     */
    public static function getOperands(): array
    {
        return [
            \GetOpt\Operand::create('action', \GetOpt\Operand::REQUIRED),
            \GetOpt\Operand::create('id', \GetOpt\Operand::OPTIONAL),
        ];
    }

    /**
     * Get help.
     */
    public function help(): Jobs
    {
        echo "listen\n";
        echo "Start job listener (blocking process)\n\n";

        echo "once\n";
        echo "Execute all leftover jobs\n\n";
        echo $this->getopt->getHelpText();

        return $this;
    }

    /**
     * Get options.
     */
    public static function getOptions(): array
    {
        return [];
    }

    /**
     * Start.
     */
    public function listen(): bool
    {
        $this->logger->info('daemon execution requested, fire up daemon', [
            'category' => get_class($this),
        ]);

        $this->hook->run('preExecuteAsyncJobs');
        $this->async->startDaemon();
    }

    /*
     * Start.
     *
     * @return bool
     */
    public function once(): bool
    {
        $this->hook->run('preExecuteAsyncJobs');
        $this->async->startOnce();
        $this->hook->run('postExecuteAsyncJobs');

        return true;
    }
}
