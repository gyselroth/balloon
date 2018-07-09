<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;
use TaskScheduler\Async;

class Hook extends AbstractHook
{
    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     */
    public function __construct(Async $async)
    {
        $this->async = $async;
    }

    /**
     * {@inheritdoc}
     */
    public function postPutFile(File $node): void
    {
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
        ]);
    }
}
