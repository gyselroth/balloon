<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Hook;

use Balloon\App\Notification\Async\Subscription as SubscriptionJob;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use TaskScheduler\Async;

class Subscription extends AbstractHook
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param Server $server
     * @param Async  $async
     */
    public function __construct(Server $server, Async $async)
    {
        $this->server = $server;
        $this->async = $async;
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        $this->notify($node);
        $user_id = (string) $this->server->getIdentity()->getId();
        $subs = $parent->getAppAttribute('Balloon\\App\\Notification', 'subscription');

        if (isset($subs[$user_id]) && $subs[$user_id]['recursive'] === true) {
            $new_subs[$user_id] = $subs[$user_id];
            $node->setAppAttribute('Balloon\\App\\Notification', 'subscription', $subs);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
        $this->notify($node);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        $this->notify($node);
    }

    /**
     * {@inheritdoc}
     */
    public function postRestoreFile(File $node, int $version): void
    {
        $this->notify($node);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        $this->notify($node);
    }

    /**
     * {@inheritdoc}
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
        $this->notify($node);
    }

    /**
     * Execute notification checks asynchronous.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    protected function notify(NodeInterface $node): bool
    {
        $this->async->addJob(SubscriptionJob::class, [
            'node' => $node->getId(),
            'user' => $this->server->getIdentity()->getId(),
        ]);

        return true;
    }
}
