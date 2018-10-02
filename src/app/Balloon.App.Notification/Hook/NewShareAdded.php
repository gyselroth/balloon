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
use Balloon\Async\Mail;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class NewShareAdded extends AbstractHook
{
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
     */
    public function __construct(Notifier $notifier, Server $server, LoggerInterface $logger)
    {
        $this->notifier = $notifier;
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if (!($node instanceof Collection)) {
            return;
        }

        $fs = $node->getFilesystem();
        $raw = $node->getRawAttributes();

        if ($node->isReference()) {
            return;
        }
        if ($node->isShared() && isset($raw['acl']) && $raw['acl'] === $node->getAttributes()['acl']) {
            return;
        }
        if (!$node->isShared()) {
            return;
        }

        $receiver = [];
        foreach ($node->getAcl() as $rule) {
            if (isset($raw['acl']) && $this->hadRole($raw['acl'], $rule['role']->getId())) {
                continue;
            }

            if ('user' === $rule['type']) {
                if (!isset($receiver[(string) $rule['role']->getId()]) && $this->checkNotify($node, $rule['role'])) {
                    $receiver[(string) $rule['role']->getId()] = $rule['role'];
                }
            } elseif ('group' === $rule['type']) {
                foreach ($rule['role']->getResolvedMembers() as $user) {
                    if (!isset($receiver[(string) $user->getId()]) && $this->checkNotify($node, $user)) {
                        $receiver[(string) $user->getId()] = $user;
                    }
                }
            }
        }

        if (!empty($receiver)) {
            $message = $this->notifier->compose('new_share_added', [
                'node' => $node,
            ]);

            $this->notifier->notify($receiver, $this->server->getIdentity(), $message);
        }
    }

    /**
     * Check if had role before.
     */
    protected function hadRole(array $acl, ObjectId $id): bool
    {
        foreach ($acl as $rule) {
            if ($rule['id'] == $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if users needs a notification and checks if mail adress is available.
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
