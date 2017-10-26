<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Async\Mail;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use Balloon\Hook\HookInterface;
use Balloon\Resource;
use Balloon\Server\User;
use Zend\Mail\Message;

class Hook extends AbstractHook
{
    /**
     * Notifications.
     *
     * @var array
     */
    protected $notifications = [];

    /**
     * Notifications.
     *
     * @var array
     */
    protected $defaults = [
        'new_share' => [
            'body' => '{owner} added a new share {share}',
            'subject' => 'new share',
            'sender' => [
                'name' => 'balloon',
                'address' => 'balloon@localhost',
            ],
        ],
    ];

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return HookInterface
     */
    public function setOptions(?Iterable $config = null): HookInterface
    {
        if (null === $config) {
            return $this;
        }

        if (isset($config['notifications'])) {
            foreach ($config['notifications'] as $name => $notify) {
                if ('1' === $notify['enabled']) {
                    $this->logger->info('enabled mail notification ['.$name.']', [
                        'category' => get_class($this),
                    ]);

                    $this->notifications[$name] = $this->defaults[$name];

                    if (!isset($notify['config'])) {
                        continue;
                    }

                    foreach ($notify['config'] as $option => $value) {
                        switch ($option) {
                            case 'body':
                            case 'subject':
                                $this->notifications[$name][$option] = $value;

                                break;
                            case 'sender':
                                $this->notifications[$name]['sender']['name'] = $value['name'];
                                $this->notifications[$name]['sender']['address'] = $value['address'];

                                break;
                        }
                    }
                } else {
                    $this->logger->debug('skip disabled mail notification ['.$name.']', [
                        'category' => get_class($this),
                    ]);
                }
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

        if (!array_key_exists('new_share', $this->notifications)) {
            return;
        }

        $fs = $node->getFilesystem();
        $async = $server->getAsync();
        $raw = $node->getRawAttributes();
        $acl = $node->getShare();
        $resource = new Resource($fs->getUser(), $this->logger, $fs);
        if ($node->isShared() && is_array($acl) && (isset($raw['acl']) && $raw['acl'] !== $node->getAcl() || !isset($raw['acl']))) {
            $receiver = [];
            foreach ($acl as $rule) {
                if ('user' === $rule['type']) {
                    $user = new User($rule['name'], $this->logger, $fs, true, false);
                    $mail = $this->checkNotify($node, $user);
                    if (null !== $mail && !in_array($mail, $receiver, true)) {
                        $receiver[] = $mail;
                    }
                } elseif ('group' === $rule['type']) {
                    foreach ($resource->getUsersByGroup($rule['id']) as $user) {
                        $mail = $this->checkNotify($node, $user);
                        if (null !== $mail && !in_array($mail, $receiver, true)) {
                            $receiver[] = $mail;
                        }
                    }
                }
            }

            if (!empty($receiver)) {
                $body = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($node) {
                    return $node->getAttribute($match[2]);
                }, $this->notifications['new_share']['body']);
                $subject = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($node) {
                    return $node->getAttribute($match[2]);
                }, $this->notifications['new_share']['subject']);

                $mail = new Message();
                $mail->setBody($body);
                $mail->setFrom(
                    $this->notifications['new_share']['sender']['address'],
                  $this->notifications['new_share']['sender']['name']
                );
                $mail->setSubject($subject);

                foreach ($receiver as $rec) {
                    $mail->setTo($rec);
                    $async->addJob(new Mail(['mail' => $mail->toString()]));
                }
            }
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
    protected function checkNotify(NodeInterface $node, User $user): ?string
    {
        if ($user->hasShare($node)) {
            $this->logger->debug('skip mail notifcation for share ['.$node->getId().'] user ['.$user->getId().'] already owns it', [
                'category' => get_class($this),
            ]);

            return null;
        }
        $mail = $user->getAttribute('mail');
        if (null === $mail) {
            $this->logger->debug('skip mail notifcation for share ['.$node->getId().'] user ['.$user->getId().'] does not have a valid mail address', [
                    'category' => get_class($this),
                ]);

            return null;
        }

        return $mail;
    }
}
