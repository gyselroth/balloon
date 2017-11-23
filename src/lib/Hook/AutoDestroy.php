<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use TaskScheduler\Async;
use Balloon\Async\AutoDestroy as Job;

class AutoDestroy extends AbstractHook
{
    /**
     * Execution interval.
     *
     * @var int
     */
    protected $interval = 28800;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param iterable $config
     */
    public function __construct(Async $async, ?Iterable $config = null)
    {
        $this->async = $async;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return HookInterface
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
        $this->async->addJobOnce(Job::class, [], [
            'interval' => $this->interval,
        ]);
    }
}
