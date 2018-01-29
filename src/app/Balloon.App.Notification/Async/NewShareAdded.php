<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Async;

use Balloon\App\Notification\Exception;
use Balloon\App\Notification\NodeMessage;
use Balloon\App\Notification\Notifier;
use Balloon\App\Notification\TemplateHandler;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class NewShareAdded extends AbstractJob
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
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

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
        $this->fs = $server->getFilesystem();
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
    public function start(): bool
    {
        $node = $this->fs->findNodeById($this->data['node']);
        $receiver = [];
        foreach ($node->getAcl() as $rule) {
            if ('user' === $rule['type']) {
                if (!isset($receiver[(string) $rule['role']->getId()]) && $this->checkNotify($node, $rule['role'])) {
                    $receiver[(string) $rule['role']->getId()] = $rule['role'];
                }
            } elseif ('group' === $rule['type']) {
                foreach ($rule['role']->getResolvedMember() as $user) {
                    if (!isset($receiver[(string) $user->getId()]) && $this->checkNotify($node, $user)) {
                        $receiver[(string) $user->getId()] = $user;
                    }
                }
            }
        }

        if (!empty($receiver)) {
            $message = new NodeMessage('new_share_added', $this->template, $node);
            $this->notifier->notify($receiver, $this->server->getUserById($node->getOwner()), $message);
        }

        return true;
    }

    /**
     * Check if users needs a notification and checks if mail adress is available.
     *
     * @param NodeInterface $node
     * @param User          $user
     *
     * @return string
     */
    protected function checkNotify(NodeInterface $node, User $user): bool
    {
        if ($user->hasShare($node)) {
            $this->logger->debug('skip notifcation for share ['.$node->getId().'] user ['.$user->getId().'] already got it', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return true;
    }
}
