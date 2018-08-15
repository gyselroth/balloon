<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Bootstrap\ContainerBuilder;
use Composer\Autoload\ClassLoader as Composer;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use TaskScheduler\Scheduler;
use TaskScheduler\Worker;
use TaskScheduler\WorkerFactoryInterface;

class WorkerFactory implements WorkerFactoryInterface
{
    /**
     * Composer.
     *
     * @var Composer
     */
    protected $composer;

    /**
     * Construct.
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * {@inheritdoc}
     */
    public function build(): Worker
    {
        $dic = ContainerBuilder::get($this->composer);

        return new Worker($dic->get(Scheduler::class), $dic->get(Database::class), $dic->get(LoggerInterface::class), $dic);
    }
}
