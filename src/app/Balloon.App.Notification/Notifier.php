<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\App\Notification\Adapter\AdapterInterface;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Cursor;
use Psr\Log\LoggerInterface;

class Notifier
{
    /**
     * Notifications.
     *
     * @var array
     */
    protected $notifications = [];

    /**
     * Adapter.
     *
     * @var array
     */
    protected $adapter = [];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Collection name.
     *
     * @var string
     */
    protected $collection_name = 'notification';

    /**
     * Constructor.
     *
     * @param Database       $db
     * @param Server         $server
     * @param LoggerInterace $logger
     * @param iterable       $config
     */
    public function __construct(Database $db, Server $server, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->db = $db;
        $this->server = $server;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Notifier
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'adapter':
                    foreach ($value as $name => $adapter) {
                        $this->injectAdapter($adapter, $name);
                    }

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Send notification.
     *
     * @param iterable $receiver
     * @param User     $sender
     * @param string   $subject
     * @param string   $body
     * @param array    $context
     *
     * @return bool
     */
    public function notify(Iterable $receiver, ?User $sender, MessageInterface $message, array $context = []): bool
    {
        if (0 === count($this->adapter)) {
            $this->logger->warning('there are no notification adapter enabled, notification can not be sent', [
                'category' => get_class($this),
            ]);

            return false;
        }

        foreach ($receiver as $user) {
            foreach ($this->adapter as $name => $adapter) {
                $this->logger->debug('send notification to user ['.$user->getId().'] via adapter ['.$name.']', [
                    'category' => get_class($this),
                ]);

                $adapter->notify($user, $sender, $message, $context);
            }
        }

        return true;
    }

    /**
     * Has adapter.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return isset($this->adapter[$name]);
    }

    /**
     * Inject adapter.
     *
     * @param AdapterInterface $adapter
     * @param string           $name
     *
     * @return Notifier
     */
    public function injectAdapter(AdapterInterface $adapter, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject notification adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this),
        ]);

        if ($this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is already registered');
        }

        $this->adapter[$name] = $adapter;

        return $this;
    }

    /**
     * Get adapter.
     *
     * @param string $name
     *
     * @return AdapterInterface
     */
    public function getAdapter(string $name): AdapterInterface
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }

    /**
     * Get adapters.
     *
     * @param array $adapters
     *
     * @return AdapterInterface[]
     */
    public function getAdapters(array $adapters = []): array
    {
        if (empty($adapter)) {
            return $this->adapter;
        }
        $list = [];
        foreach ($adapter as $name) {
            if (!$this->hasAdapter($name)) {
                throw new Exception('adapter '.$name.' is not registered');
            }
            $list[$name] = $this->adapter[$name];
        }

        return $list;
    }

    /**
     * Add notification.
     *
     * @param array            $receiver
     * @param User             $user
     * @param MessageInterface $message
     * @param array            $context
     *
     * @return ObjectId
     */
    public function postNotification(User $receiver, ?User $sender, MessageInterface $message, array $context = []): ObjectId
    {
        $data = [
            'context' => $context,
            'subject' => $message->getSubject($receiver),
            'body' => $message->getBody($receiver),
            'receiver' => $receiver->getId(),
        ];

        if ($sender instanceof User) {
            $data['sender'] = $sender->getId();
        }

        $result = $this->db->{$this->collection_name}->insertOne($data);

        return $result->getInsertedId();
    }

    /**
     * Get notifications.
     *
     * @return Cursor
     */
    public function getNotifications(): Cursor
    {
        $result = $this->db->{$this->collection_name}->find(['receiver' => $this->server->getIdentity()->getId()]);

        return $result;
    }

    /**
     * Get notifications.
     *
     * @param ObjectId $id
     *
     * @return bool
     */
    public function deleteNotification(ObjectId $id): bool
    {
        $result = $this->db->{$this->collection_name}->deleteOne([
            '_id' => $id,
            'receiver' => $this->server->getIdentity()->getId(),
        ]);

        if (null === $result) {
            throw new Exception('notification not found');
        }

        $this->logger->debug('notification ['.$id.'] removed from user ['.$this->server->getIdentity()->getId().']', [
            'category' => get_class($this),
        ]);

        return true;
    }

    /**
     * Subscribe to node updates.
     *
     * @param NodeInterface $node
     * @param bool          $subscribe
     * @param bool          $exclude_me
     * @param bool          $recursive
     *
     * @return bool
     */
    public function subscribeNode(NodeInterface $node, bool $subscribe = true, bool $exclude_me = true, bool $recursive = false): bool
    {
        $subs = $node->getAppAttribute(__NAMESPACE__, 'subscription');
        $user_id = (string) $this->server->getIdentity()->getId();

        if (true === $subscribe) {
            $this->logger->debug('user ['.$this->server->getIdentity()->getId().'] subribes node ['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            $subscription = [
                'timestamp' => new UTCDateTime(),
                'exclude_me' => $exclude_me,
                'recursive' => $recursive,
            ];

            $subs[$user_id] = $subscription;
            $node->setAppAttribute(__NAMESPACE__, 'subscription', $subs);
            if ($node instanceof Collection && $recursive === true) {
                $node->doRecursiveAction(function ($child) use ($subscription, $user_id) {
                    $subs = $child->getAppAttribute(__NAMESPACE__, 'subscription');
                    $subs[$user_id] = $subscription;
                    $child->setAppAttribute(__NAMESPACE__, 'subscription', $subs);
                });
            }
        } else {
            $this->logger->debug('user ['.$this->server->getIdentity()->getId().'] unsubribes node ['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            if (isset($subs[$user_id])) {
                unset($subs[$user_id]);
            }

            $node->setAppAttribute(__NAMESPACE__, 'subscription', $subs);

            if ($node instanceof Collection && $recursive === true) {
                $node->doRecursiveAction(function ($child) use ($subscription, $user_id) {
                    $subs = $child->getAppAttribute(__NAMESPACE__, 'subscription');

                    if (isset($subs[$user_id])) {
                        unset($subs[$user_id]);
                    }
                    $child->setAppAttribute(__NAMESPACE__, 'subscription', $subs);
                });
            }
        }

        return true;
    }
}
