<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Async\AutoDestroy as Job;
use TaskScheduler\JobInterface;
use TaskScheduler\Scheduler;

class AutoDestroy extends AbstractHook
{
    /**
     * Execution interval.
     *
     * @var int
     */
    protected $interval = 28800;

    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Constructor.
     */
    public function __construct(Scheduler $scheduler, ?Iterable $config = null)
    {
        $this->scheduler = $scheduler;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(?Iterable $config = null): HookInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'interval':
                    $this->{$option} = (int) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function preExecuteAsyncJobs(): void
    {
        if ($this->interval === 0) {
            foreach ($this->scheduler->getJobs([
                'class' => Job::class,
                'status' => ['$lte' => JobInterface::STATUS_PROCESSING],
            ]) as $job) {
                $this->scheduler->cancelJob($job->getId());
            }

            return;
        }

        $this->scheduler->addJobOnce(Job::class, [], [
            'interval' => $this->interval,
        ]);
    }
}
