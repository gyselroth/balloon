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
use Balloon\App\Notification\Notifier;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use Psr\Log\LoggerInterface;

class Subscription extends AbstractHook
{
    /**
     * Body.
     *
     * @var string
     */
    protected $body = "Hi {user.name} \n\r There have been made changes in your balloon directory.";

    /**
     * Subject.
     *
     * @var string
     */
    protected $subject = 'change';

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

    /**
     * User.
     *
     * @var User
     */
    protected $user;

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
        $this->user = $server->getIdentity();
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
        if (isset($subs[(string) $this->user->getId()])) {
            $this->logger->info('user ['.$this->user->getId().'] got a subscription for node ['.$collection->getId().']', [
                'category' => get_class($this),
            ]);
        } else {
            $this->logger->info('user ['.$this->user->getId().'] has no subscription for node ['.$collection->getId().']', [
                'category' => get_class($this),
            ]);

            return;
        }

        $throttle = $collection->getAppAttribute('Balloon\\App\\Notification', 'notification_throttle');
        if (is_array($throttle) && isset($throttle[(string) $collection->getId()])) {
            $last = $throttle[(string) $collection->getId()];
        }

        $body = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($collection) {
            return (string) $collection;
            //return $collection->getAttribute($match[2]);
        }, $this->body);
        $subject = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($collection) {
            return (string) $collection;
            //return $collection->getAttribute($match[2]);
        }, $this->subject);

        $this->notifier->notify($this->user, null, $subject, $body);
    }
}
