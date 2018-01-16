<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
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
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_CREATE,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_CREATE,
            'storage' => $node->getAttributes()['storage'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => $force === true ? Job::ACTION_DELETE : Job::ACTION_UPDATE,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => $force === true ? Job::ACTION_DELETE : Job::ACTION_UPDATE,
            'storage' => $node->getAttributes()['storage'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_UPDATE,
        ]);
    }
}
