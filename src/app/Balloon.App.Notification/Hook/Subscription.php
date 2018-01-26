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
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use Psr\Log\LoggerInterface;

class Subscription extends AbstractHook
{
    /**
     * Notification throttle.
     *
     * @var int
     */
    protected $notification_throttle = 3600;

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
     * @return AbstractHook
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'body':
                case 'subject':
                    $this->{$option} = (string) $value;

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
        $this->notify($parent);
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
        $this->notify($parent);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        $this->notify($node->getParent());
    }

    /**
     * {@inheritdoc}
     */
    public function postRestoreFile(File $node, int $version): void
    {
        $this->notify($node->getParent());
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        $this->notify($node->getParent());
    }

    /**
     * {@inheritdoc}
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
        $this->notify($node->getParent());
    }

    /**
     * Check if we need to notify.
     *
     * @param Collection $collection
     */
    protected function notify(Collection $collection): void
    {
        $subs = $collection->getAppAttribute('Balloon\\App\\Notification', 'subscription');
        if (!is_array($subs)) {
            return;
        }

        $user_id = (string) $this->server->getIdentity()->getId();

        $throttle = $collection->getAppAttribute('Balloon\\App\\Notification', 'notification_throttle');
        if (is_array($throttle) && isset($throttle[(string) $collection->getId()])) {
            $last = $throttle[(string) $collection->getId()];
        }

        if (isset($subs[$user_id]) && $subs[$user_id]['exclude_me'] === true) {
            $this->logger->debug('skip message for user ['.$user_id.'], user excludes own actions in node ['.$collection->getId().']', [
                'category' => get_class($this),
            ]);

            unset($subs[$user_id]);
        }

        $receiver = $this->server->getUsersById(array_keys($subs));
        $message = new NodeMessage('subscription', $this->template, $collection);
        $this->notifier->notify($receiver, $this->server->getIdentity(), $message);
    }
}
