<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
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
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        $this->scheduler->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_CREATE,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
        $this->scheduler->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_CREATE,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $force) {
            return;
        }

        $this->scheduler->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_DELETE_COLLECTION,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $force) {
            return;
        }

        $this->scheduler->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_DELETE_FILE,
            'hash' => $node->getHash(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if ($node instanceof Collection) {
            $raw = $node->getRawAttributes();
            if ($node->isShared() && !isset($raw['acl'])) {
                $this->scheduler->addJob(Job::class, [
                    'id' => $node->getId(),
                    'action' => Job::ACTION_ADD_SHARE,
                ]);
            } elseif (!$node->isShared() && isset($raw['acl']) && $raw['acl'] !== []) {
                $this->scheduler->addJob(Job::class, [
                    'id' => $node->getId(),
                    'action' => Job::ACTION_DELETE_SHARE,
                ]);
            }
        }

        $this->scheduler->addJob(Job::class, [
            'id' => $node->getId(),
            'action' => Job::ACTION_UPDATE,
            'hash' => ($node instanceof File) ? $node->getRawAttributes()['hash'] : null,
        ]);
    }
}
