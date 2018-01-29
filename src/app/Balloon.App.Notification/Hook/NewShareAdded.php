<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Hook;

use Balloon\App\Notification\Async\NewShareAdded as NewShareAddedJob;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use TaskScheduler\Async;

class NewShareAdded extends AbstractHook
{
    /**
     * Taskscheduler.
     *
     * @var TaskScheduler
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param Async $async
     */
    public function __construct(Async $async)
    {
        $this->async = $async;
    }

    /**
     * {@inheritdoc}
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if (!($node instanceof Collection)) {
            return;
        }

        $fs = $node->getFilesystem();
        $raw = $node->getRawAttributes();

        if (!$node->isShared() && (isset($raw['acl']) && $raw['acl'] === $node->getAcl())) {
            return;
        }

        $this->async->addJob(NewShareAddedJob::class, [
            'node' => $node->getId(),
        ]);
    }
}
