<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Hook;

use Balloon\Async\Mail;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Hook\HookInterface;
use Balloon\Resource;
use Balloon\App\Notification\App as Notification;
use Balloon\Server;
use Zend\Mail\Message;
use MongoDB\BSON\ObjectId;
use Balloon\Server\User;

class NewShareAdded extends AbstractHook
{
    /**
     * Body
     *
     * @var string
     */
    protected $body = 'added a new share {share}';


    /**
     * Subject
     *
     * @var string
     */
    protected $subject = 'new share';


    /**
     * Notifier
     *
     * @var Notification
     */
    protected $notifier;


    /**
     * Server
     *
     * @var Server
     */
    protected $server;


    /**
     * Constructor
     *
     * @param Notification $notifier
     * @param Server $server
     */
    public function __construct(Notification $notifier, Server $server)
    {
        $this->notifier = $notifier;
        $this->server = $server;
        $this->setOptions($notifier->getNotificationConfig($this));
    }


    /**
     * Set config
     *
     * @param Iterable $config
     * @return AbstractHook
     */
    public function setOptions(?Iterable $config=null): AbstractHook
    {
        if($config === null) {
            return $this;
        }

        foreach($config as $option => $value) {
            switch($option) {
                case 'body':
                case 'subject':
                    $this->{$option} = (string)$value;
                break;
            }
        }

        return $this;
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

        if (!$node->isShared() && (isset($raw['acl']) && $raw['acl'] === $node->getShareAcl())) {
            return;
        }

        $receiver = [];
        foreach ($node->getShareAcl() as $rule) {
            if ('user' === $rule['type']) {
                $user = $this->server->getUserById(new ObjectId($rule['id']));
                if(!isset($receiver[(string)$user->getId()]) && $this->checkNotify($node, $user)) {
                    $receiver[(string)$user->getId()] = $user;
                }
            } elseif ('group' === $rule['type']) {
                foreach ($resource->getGroupById($rule['id'])->getResolvedMember() as $user) {
                    if(!isset($receiver[(string)$user->getId()]) && $this->checkNotify($node, $user)) {
                        $receiver[(string)$user->getId()] = $user;
                    }
                }
            }
        }

        if (!empty($receiver)) {
            $body = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($node) {
                return $node->getAttribute($match[2]);
            }, $this->body);
            $subject = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($node) {
                return $node->getAttribute($match[2]);
            }, $this->subject);


            $this->notifier->notify($receiver, $subject, $body);
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
