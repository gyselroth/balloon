<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Cli\Console;

use Balloon\Hook;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;
use TaskScheduler\Queue;

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
     * Queue.
     *
     * @var Queue
     */
    protected $queue;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Constructor.
     */
    public function __construct(Hook $hook, Queue $queue, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->queue = $queue;
        $this->hook = $hook;
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->queue = $queue;
    }

    /**
     * Start.
     */
    public function __invoke(): bool
    {
        $this->logger->info('daemon execution requested, fire up daemon', [
            'category' => get_class($this),
        ]);

        $this->hook->run('preExecuteAsyncJobs');
        $this->queue->process();

        return true;
    }

    // Get operands
    public static function getOperands(): array
    {
        return [];
    }

    /**
     * Get options.
     */
    public static function getOptions(): array
    {
        return [];
    }
}
