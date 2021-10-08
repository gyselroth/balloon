<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;
use TaskScheduler\Scheduler;

class Hook extends AbstractHook
{
    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Constructor.
     */
    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function postPutFile(File $node): void
    {
        $this->scheduler->addJob(Job::class, [
            'id' => $node->getId(),
        ]);
    }
}
