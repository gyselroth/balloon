<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Async;
use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;

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
     *
     * @param Async $async
     */
    public function __construct(Async $async)
    {
        $this->async = $async;
    }

    /**
     * {@inheritDoc}
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
        $this->addJob($node);
    }

    /**
     * {@inheritDoc}
     */
    public function postRestoreFile(File $node, int $version): void
    {
        $this->addJob($node);
    }

    /**
     * Add job.
     *
     * @param File $node
     */
    protected function addJob(File $node): void
    {
        $slaves = $node->getAppAttribute(__NAMESPACE__, 'slaves');
        if (null === $slaves) {
            return;
        }

        foreach ($slaves as $id => $slave) {
            $this->async->addJob(Job::class, [
                'node' => $node->getId(),
                'slave' => $id,
            ]);
        }
    }
}
