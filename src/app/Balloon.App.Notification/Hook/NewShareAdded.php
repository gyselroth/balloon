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
use Balloon\Async\Mail;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
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
     * Node attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $template;

    /**
     * Constructor.
     *
     * @param Notification $notifier
     * @param Server       $server
     */
    public function __construct(Notifier $notifier, Server $server, LoggerInterface $logger, TemplateHandler $template, ?Iterable $config = null)
    {
        $this->notifier = $notifier;
        $this->server = $server;
        $this->logger = $logger;
        $this->template = $template;
    }

    /**
     * Run: postSaveNodeAttributes.
     *
     * Executed post node attributes were saved to mongodb
     *
     * @param NodeInterface $node
     * @param array         $attributes
     * @param array         $remove
     * @param string        $recursion
     * @param bool          $recursion_first
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if (!($node instanceof Collection)) {
            return;
        }

        $fs = $node->getFilesystem();
        $raw = $node->getRawAttributes();

        if (!$node->isShared() && (isset($raw['acl']) && $raw['acl'] === $node->getAcl())) {
            return;
        }

        $receiver = [];
        foreach ($node->getAcl() as $rule) {
            if ('user' === $rule['type']) {
                $user = $this->server->getUserById(new ObjectId($rule['id']));
                if (!isset($receiver[(string) $user->getId()]) && $this->checkNotify($node, $user)) {
                    $receiver[(string) $user->getId()] = $user;
                }
            } elseif ('group' === $rule['type']) {
                foreach ($this->server->getGroupById(new ObjectId($rule['id']))->getResolvedMember() as $user) {
                    if (!isset($receiver[(string) $user->getId()]) && $this->checkNotify($node, $user)) {
                        $receiver[(string) $user->getId()] = $user;
                    }
                }
            }
        }

        if (!empty($receiver)) {
            $message = new NodeMessage('new_share_added', $this->template, $node);
            $this->notifier->notify($receiver, $this->server->getIdentity(), $message);
        }
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
