<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\App\Notification\Adapter\AdapterInterface;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
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
     * Message handler.
     *
     * @var TemplateHandler
     */
    protected $template;

    /**
     * Notification throttle.
     *
     * @var int
     */
    protected $notification_throttle = 120;

    /**
     * Constructor.
     */
    public function __construct(Database $db, Server $server, LoggerInterface $logger, TemplateHandler $template, array $config = [])
    {
        $this->logger = $logger;
        $this->db = $db;
        $this->server = $server;
        $this->template = $template;
        $this->setOptions($config);
    }

    /**
     * Set config.
     */
    public function setOptions(array $config = []): self
    {
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
     * Get notification throttle time.
     */
    public function getThrottleTime(): int
    {
        return $this->notification_throttle;
    }

    /**
     * Node message factory.
     */
    public function compose(string $type, array $context = []): MessageInterface
    {
        return new Message($type, $this->template, $context);
    }

    /**
     * Send notification.
     */
    public function notify(iterable $receiver, ?User $sender, MessageInterface $message): bool
    {
        if (0 === count($this->adapter)) {
            throw new Exception\NoAdapterAvailable('there are no notification adapter enabled, notification can not be sent');
        }

        foreach ($receiver as $user) {
            foreach ($this->adapter as $name => $adapter) {
                $this->logger->debug('send notification to user ['.$user->getId().'] via adapter ['.$name.']', [
                    'category' => static::class,
                ]);

                $adapter->notify($user, $sender, $message);
            }
        }

        return true;
    }

    /**
     * Has adapter.
     */
    public function hasAdapter(string $name): bool
    {
        return isset($this->adapter[$name]);
    }

    /**
     * Inject adapter.
     */
    public function injectAdapter(AdapterInterface $adapter, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject notification adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => static::class,
        ]);

        if ($this->hasAdapter($name)) {
            throw new Exception\AdapterNotUnique('adapter '.$name.' is already registered');
        }

        $this->adapter[$name] = $adapter;

        return $this;
    }

    /**
     * Get adapter.
     */
    public function getAdapter(string $name): AdapterInterface
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception\AdapterNotFound('adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }

    /**
     * Get adapters.
     */
    public function getAdapters(array $adapters = []): array
    {
        if (empty($adapter)) {
            return $this->adapter;
        }
        $list = [];
        foreach ($adapter as $name) {
            if (!$this->hasAdapter($name)) {
                throw new Exception\AdapterNotFound('adapter '.$name.' is not registered');
            }
            $list[$name] = $this->adapter[$name];
        }

        return $list;
    }

    /**
     * Add notification.
     */
    public function postNotification(User $receiver, ?User $sender, MessageInterface $message): ObjectId
    {
        $data = [
            'subject' => $message->getSubject($receiver),
            'body' => $message->getBody($receiver),
            'receiver' => $receiver->getId(),
            'locale' => $receiver->getAttributes()['locale'],
            'type' => $message->getType(),
        ];

        if ($sender instanceof User) {
            $data['sender'] = $sender->getId();
        }

        if (isset($message->getContext()['node'])) {
            $data['node'] = $message->getContext()['node']->getId();
        }

        $result = $this->db->{$this->collection_name}->insertOne($data);

        return $result->getInsertedId();
    }

    /**
     * Get notifications.
     */
    public function getNotifications(User $user, array $query = [], ?int $offset = null, ?int $limit = null, ?int &$total = null): iterable
    {
        $filter = ['receiver' => $user->getId()];
        if (!empty($query)) {
            $filter = [
                '$and' => [
                    $filter,
                    $query,
                ],
            ];
        }

        $total = $this->db->{$this->collection_name}->count($filter);
        $result = $this->db->{$this->collection_name}->find($filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);

        return $result;
    }

    /**
     * Get notification.
     */
    public function getNotification(ObjectId $id): array
    {
        $result = $this->db->{$this->collection_name}->findOne([
            '_id' => $id,
            'receiver' => $this->server->getIdentity()->getId(),
        ]);

        if ($result === null) {
            throw new Exception\NotificationNotFound('notification not found');
        }

        return $result;
    }

    /**
     * Get notifications.
     */
    public function deleteNotification(ObjectId $id): bool
    {
        $result = $this->db->{$this->collection_name}->deleteOne([
            '_id' => $id,
            'receiver' => $this->server->getIdentity()->getId(),
        ]);

        if (null === $result) {
            throw new Exception\NotificationNotFound('notification not found');
        }

        $this->logger->debug('notification ['.$id.'] removed from user ['.$this->server->getIdentity()->getId().']', [
            'category' => static::class,
        ]);

        return true;
    }

    /**
     * Throttle subscriptions.
     */
    public function throttleSubscriptions(array $subscriptions): Notifier
    {
        $this->db->subscription->updateMany([
            '_id' => [
                '$in' => $subscriptions,
            ],
        ], [
            '$set' => [
                'last_notification' => new UTCDateTime(),
            ],
        ]);

        return $this;
    }

    /**
     * Get subscription.
     */
    public function getSubscription(NodeInterface $node, User $user): ?array
    {
        $node_id = $node->isReference() ? $node->getShareId() : $node->getId();

        return $this->db->subscription->findOne([
            'node' => $node_id,
            'user' => $user->getId(),
        ]);
    }

    /**
     * Get subscriptions.
     */
    public function getSubscriptions(NodeInterface $node): iterable
    {
        $node_id = $node->isReference() ? $node->getShareId() : $node->getId();

        return $this->db->subscription->find([
            'node' => $node_id,
        ]);
    }

    /**
     * Get subscriptions.
     */
    public function getAllSubscriptions(NodeInterface $node): iterable
    {
        $sub_id = $node->isReference() ? $node->getShareId() : $node->getId();

        $ids = [$sub_id];
        foreach ($node->getParents() as $parent) {
            $ids[] = $parent->isReference() ? $parent->getShareId() : $parent->getId();

            if ($parent->isShare()) {
                break;
            }
        }

        $parents = [$ids[0]];

        if (count($ids) > 1) {
            $parents[] = $ids[1];
        }

        return $this->db->subscription->find([
            '$or' => [
                [
                    'node' => ['$in' => $ids],
                    'recursive' => true,
                ],
                [
                    'node' => ['$in' => $parents],
                ],
            ],
        ]);
    }

    /**
     * Subscribe to node updates.
     */
    public function subscribeNode(NodeInterface $node, bool $subscribe = true, bool $exclude_me = true, bool $recursive = false, ?int $throttle = null): bool
    {
        $node_id = $node->isReference() ? $node->getShareId() : $node->getId();
        $user_id = $this->server->getIdentity()->getId();

        if (true === $subscribe) {
            $this->logger->debug('user ['.$this->server->getIdentity()->getId().'] subscribes node ['.$node->getId().']', [
                'category' => static::class,
            ]);

            $subscription = [
                'timestamp' => new UTCDateTime(),
                'exclude_me' => $exclude_me,
                'recursive' => $recursive,
                'throttle' => $throttle,
                'user' => $user_id,
                'node' => $node_id,
            ];

            $this->db->subscription->replaceOne(
                [
                'user' => $subscription['user'],
                'node' => $subscription['node'],
            ],
                $subscription,
                [
                'upsert' => true,
            ]
            );
        } else {
            $this->logger->debug('user ['.$this->server->getIdentity()->getId().'] unsubscribes node ['.$node->getId().']', [
                'category' => static::class,
            ]);

            $this->db->subscription->deleteOne([
                'user' => $user_id,
                'node' => $node_id,
            ]);

            if ($node instanceof Collection && $recursive === true) {
                $db = $this->db;
                $node->doRecursiveAction(function ($child) use ($db, $node_id, $user_id) {
                    $db->subscription->deleteOne([
                        'user' => $user_id,
                        'node' => $node_id,
                    ]);
                });
            }
        }

        return true;
    }
}
