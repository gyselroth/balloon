<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Console;

use Balloon\App\Elasticsearch\Job;
use Balloon\Filesystem;
use Balloon\Server;
use GetOpt\GetOpt;
use InvalidArgumentException;
use MongoDB\Driver\Exception\ServerException;
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
     * Bulk.
     *
     * @var int
     */
    protected $bulk = 200;

    /**
     * Constructor.
     */
    public function __construct(GetOpt $getopt, Server $server, Scheduler $scheduler, LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->fs = $server->getFilesystem();
        $this->scheduler = $scheduler;
        $this->setOptions($config);
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

        $total = $this->fs->countNodes();
        $this->logger->info('reindex elasticsearch, nodes left: {total}/{total}', [
            'category' => get_class($this),
            'total' => $total,
        ]);

        $stack = [];
        $done = 0;
        $result = false;
        $skip = 0;

        while ($result === false) {
            try {
                $this->index($total, $skip);
                $result = true;
            } catch (ServerException $e) {
                $skip -= $this->bulk;

                $this->logger->error('cursor timeout captchered, restart from {skip}', [
                    'category' => get_class($this),
                    'exception' => $e,
                    'skip' => $skip,
                ]);
            }
        }

        return true;
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = [])
    {
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'bulk':
                    $this->{$key} = (int) $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$key.' given');
            }
        }

        return $this;
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

    /**
     * Index.
     */
    protected function index(int $total = 0, int &$done = 0): bool
    {
        $stack = [];

        foreach ($this->fs->findNodesByFilter([], $done) as $node) {
            $stack[] = $this->scheduler->addJob(Job::class, [
                'id' => $node->getId(),
                'action' => Job::ACTION_CREATE,
            ]);

            if (count($stack) >= $this->bulk) {
                $this->logger->info('waiting for ['.$this->bulk.'] jobs to be finished', [
                    'category' => get_class($this),
                ]);

                $done += $this->bulk;
                $this->logger->info('reindex elasticsearch {percent}, nodes left: {done}/{total}', [
                    'category' => get_class($this),
                    'percent' => round($done / $total * 100, 1).'%',
                    'total' => $total,
                    'done' => $done,
                ]);

                $this->scheduler->waitFor($stack);
                $stack = [];
            }
        }

        return true;
    }
}
