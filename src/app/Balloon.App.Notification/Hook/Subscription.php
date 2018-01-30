<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Hook;

use Balloon\App\Notification\Exception;
use Balloon\App\Notification\NodeMessage;
use Balloon\App\Notification\Notifier;
use Balloon\App\Notification\TemplateHandler;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use MongoDB\BSON\UTCDateTime;
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
     * Template handler.
     *
     * @var TemplateHandler
     */
    protected $template;

    /**
     * Constructor.
     *
     * @param Notification $notifier
     * @param Server       $server
     */
    public function __construct(Notifier $notifier, Server $server, TemplateHandler $template, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->notifier = $notifier;
        $this->template = $template;
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
                    throw new Exception('invalid option '.$option.' given');
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
            $subs = array_keys((array) $parent->getAppAttribute('Balloon\\App\\Notification', 'subscription'));
            $parents = $this->getReceiver($parent);

            $blacklist = array_diff($subs, $parents);
            $receiver = array_diff(array_unique(array_merge($receiver, $parents)), $blacklist);
        }

        if (empty($receiver)) {
            $this->logger->debug('skip subscription notification for node ['.$node->getId().'] due empty receiver list', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $receiver = $this->server->getUsersById($receiver);
        $message = new NodeMessage('subscription', $this->template, $node);

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
        $subs = $node->getAppAttribute('Balloon\\App\\Notification', 'subscription');
        if (!is_array($subs)) {
            return [];
        }

        $update = $subs;

        foreach ($subs as $key => $subscription) {
            if (isset($subscription['last_notification']) && ($subscription['last_notification']->toDateTime()->format('U') + $this->notification_throttle) > time()) {
                $this->logger->debug('skip message for user ['.$key.'], message within throttle time range of ['.$this->notification_throttle.'s]', [
                    'category' => get_class($this),
                ]);

                unset($subs[$key]);
            } else {
                $update[$key]['last_notification'] = new UTCDateTime();
            }
        }

        $node->setAppAttribute('Balloon\\App\\Notification', 'subscription', $update);

        if ($this->server->getIdentity() !== null) {
            $user_id = (string) $this->server->getIdentity()->getId();
            if (isset($subs[$user_id]) && $subs[$user_id]['exclude_me'] === true) {
                $this->logger->debug('skip message for user ['.$user_id.'], user excludes own actions in node ['.$node->getId().']', [
                    'category' => get_class($this),
                ]);

                unset($subs[$user_id]);
            }
        }

        return array_keys($subs);
    }
}
