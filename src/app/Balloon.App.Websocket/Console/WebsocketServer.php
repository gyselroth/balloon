<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Websocket\Console;

use Balloon\Hook;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;
use TaskScheduler\Queue;
use TaskScheduler\Scheduler;
use League\Event\Emitter;
use MongoDB\Database;
use Balloon\App\Websocket\Server;

class WebsocketServer
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
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Constructor.
     */
    public function __construct(Server $server, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->server = $server;
        $this->logger = $logger;
        $this->getopt = $getopt;
    }

    /**
     * Start.
     */
    public function __invoke(): bool
    {
        $this->server->start();

        return true;
    }

    /**
     * Get operands.
     */
    public static function getOperands(): array
    {
        return [];
    }

    /**
     * Get options.
     */
    public static function getOptions(): array
    {
        return [
            \GetOpt\Option::create('f', 'flush')->setDescription('Flush queue before start (Attention all jobs get removed)'),
        ];
    }
}
