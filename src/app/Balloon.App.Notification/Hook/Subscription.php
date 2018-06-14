<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Hook;

use Balloon\App\Notification\Notifier;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Subscription extends AbstractHook
{
    /**
     * Notification throttle.
     *
     * @var int
     */
    protected $notification_throttle = 120;

    /**
     * Notifier.
     *
     * @var Notifier
     */
    protected $notifier;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param Notification $notifier
     * @param Server       $server
     */
    public function __construct(Notifier $notifier, Server $server, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->notifier = $notifier;
        $this->server = $server;
        $this->setOptions($config);
        $this->logger = $logger;
    }

    /**
     * Set config.
     *
     * @param iterable $config
     *
     * @return Subscription
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'notification_throttle':
                    $this->{$option} = (int) $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        $this->notify($node);
        $subscription = $this->notifier->getSubscription($parent, $this->server->getIdentity());
        if ($subscription !== null && $subscription['recursive'] === true) {
            $this->notifier->subscribeNode($node);
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
     * Check if we need to notify.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    protected function notify(NodeInterface $node): bool
    {
        $receiver = $this->getReceiver($node);
        $parent = $node->getParent();
        if ($parent !== null) {
            $parents = $this->getReceiver($parent);
            $receiver = array_merge($parents, $receiver);
        }

        if (empty($receiver)) {
            $this->logger->debug('skip subscription notification for node ['.$node->getId().'] due empty receiver list', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $this->notifier->throttleSubscriptions($node, $receiver);
        $receiver = $this->server->getUsers(['_id' => ['$in' => $receiver]]);
        $message = $this->notifier->nodeMessage('subscription', $node);

        return $this->notifier->notify($receiver, $this->server->getIdentity(), $message);
    }

    /**
     * Get receiver list.
     *
     * @param NodeInterface $node
     *
     * @return array
     */
    protected function getReceiver(NodeInterface $node): array
    {
        $subs = $this->notifier->getSubscriptions($node);

        $receiver = [];
        $user_id = null;
        if ($this->server->getIdentity() instanceof User) {
            $user_id = $this->server->getIdentity()->getId();
        }

        foreach ($subs as $key => $subscription) {
            if (isset($subscription['last_notification']) && ($subscription['last_notification']->toDateTime()->format('U') + $this->notification_throttle) > time()) {
                $this->logger->debug('skip message for user ['.$subscription['user'].'], message within throttle time range of ['.$this->notification_throttle.'s]', [
                    'category' => get_class($this),
                ]);
            } elseif ($subscription['user'] == $user_id && $subscription['exclude_me'] === true) {
                $this->logger->debug('skip message for user ['.$user_id.'], user excludes own actions in node ['.$node->getId().']', [
                    'category' => get_class($this),
                ]);
            } else {
                $receiver[] = $subscription['user'];
            }
        }

        $this->notifier->throttleSubscriptions($node, $receiver);

        return $receiver;
    }
}
