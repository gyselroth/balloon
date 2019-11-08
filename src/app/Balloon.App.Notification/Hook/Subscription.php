<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Hook;

use Balloon\App\Notification\Notifier;
use Balloon\App\Notification\Exception;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use Generator;
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
     * ACL.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Constructor.
     */
    public function __construct(Notifier $notifier, Server $server, Acl $acl, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->notifier = $notifier;
        $this->server = $server;
        $this->setOptions($config);
        $this->logger = $logger;
        $this->acl = $acl;
    }

    /**
     * Set config.
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
    public function postPutFile(File $node): void
    {
        $this->notify($node);
    }

    /**
     * {@inheritdoc}
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        $raw = $node->getRawAttributes();
        if (in_array('parent', $attributes, true) && $raw['parent'] !== $node->getAttributes()['parent']) {
            $this->notify($node);
        }
    }

    /**
     * Check if we need to notify.
     */
    protected function notify(NodeInterface $node): bool
    {
        if($node instanceof File && $node->isTemporaryFile()) {
            $this->logger->debug('skip subscription notification for node temporary file ['.$node->getId().'] (matches temporary file pattern)', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $receiver = $this->getReceiver($node);
        $this->send($node, $node, $receiver);

        return true;
    }

    /**
     * Send.
     */
    protected function send(NodeInterface $node, NodeInterface $subscription, array $receiver)
    {
        if (empty($receiver)) {
            $this->logger->debug('skip subscription notification for node ['.$node->getId().'] due empty receiver list', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $receiver = $this->server->getUsers(['_id' => ['$in' => $receiver]]);
        $receiver = $this->filterAccess($node, $receiver);

        $message = $this->notifier->compose('subscription', [
            'subscription' => $subscription,
            'node' => $node,
        ]);

        try {
            return $this->notifier->notify($receiver, $this->server->getIdentity(), $message);
        } catch(Exception\NoAdapterAvailable $e) {
            $this->logger->error('subscription notification could not be sent', [
                'category' => get_class($this),
                'exception' => $e
            ]);
        }
    }

    /**
     * Get receiver list.
     */
    protected function getReceiver(NodeInterface $node): array
    {
        $subs = $this->notifier->getAllSubscriptions($node);

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

    /**
     * Only send notifcation if node is accessible by user.
     */
    protected function filterAccess(NodeInterface $node, iterable $receiver): Generator
    {
        $users = [];

        foreach ($receiver as $user) {
            if ($this->acl->isAllowed($node, 'r', $user)) {
                $users[] = $user->getId();
                yield $user;
            } else {
                $this->logger->debug('skip message for user ['.$user->getId().'], node ['.$node->getId().'] not accessible by this user', [
                    'category' => get_class($this),
                ]);
            }
        }

        $this->notifier->throttleSubscriptions($node, $users);
    }
}
