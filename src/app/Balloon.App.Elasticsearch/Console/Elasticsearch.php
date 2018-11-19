<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Console;

use Balloon\App\Elasticsearch\Job;
use Balloon\Server;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;
use TaskScheduler\Scheduler;

class Elasticsearch
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
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     */
    public function __construct(GetOpt $getopt, Server $server, Scheduler $scheduler, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->fs = $server->getFilesystem();
        $this->scheduler = $scheduler;
    }

    /**
     * Reindex elasticsearch.
     */
    public function __invoke(): bool
    {
        if (!in_array('reindex', $this->getopt->getOperands())) {
            echo $this->getopt->getHelpText();

            return false;
        }

        $this->logger->info('reindex elasticsearch requested, this is an async task, be sure you have enaugh workers running.', [
            'category' => get_class($this),
        ]);

        foreach ($this->fs->findNodesByFilter([]) as $node) {
            $this->scheduler->addJob(Job::class, [
                'id' => $node->getId(),
                'action' => Job::ACTION_CREATE,
            ]);
        }

        return true;
    }

    /**
     * Get operands.
     */
    public static function getOperands(): array
    {
        return [
            \GetOpt\Operand::create('reindex')->setDescription('Reindex entire elasticsearch indices, note this may take a while according to your number of nodes)'),
        ];
    }

    /**
     * Get options.
     */
    public static function getOptions(): array
    {
        return [];
    }
}
