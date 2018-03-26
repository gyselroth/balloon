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
     *
     * @param App             $app
     * @param Async           $async
     * @param LoggerInterface $logger
     * @param GetOpt          $getopt
     */
    public function __construct(Hook $hook, Async $async, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->async = $async;
        $this->hook = $hook;
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->async = $async;
    }

    /**
     * Start.
     *
     * @return bool
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
