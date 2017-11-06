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

use Balloon\Hook\AbstractHook;
use Balloon\Hook\HookInterface;
use Balloon\App\AppInterface;
use MongoDB\BSON\UTCDateTime;
use Balloon\Async;
use Balloon\Async\CleanTrash as Job;

class CleanTrash extends AbstractHook
{
    /**
     * Execution interval
     *
     * @var int
     */
    protected $interval = 3600;

    /**
     * max age.
     *
     * @var int
     */
    protected $max_age = 2592000;


    /**
     * Last execution
     *
     * @var int
     */
    protected $last_execution;


    /**
     * Async
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor
     *
     * @param Iterable $config
     */
    public function __construct(Async $async, ?Iterable $config=null)
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
                case 'max_age':
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
     * {@inheritDoc}
     */
    public function preExecuteAsyncJobs(): void
    {
        if($this->last_execution + $this->interval <= time()) {
            $this->async->addJobOnce(Job::class, ['max_age' => $this->max_age],
                true, $this->last_execution);
            $this->last_execution = time();
        }
    }
}
