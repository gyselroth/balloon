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

use Balloon\App\Notification\Adapter\AdapterInterface;
use Balloon\App\Notification\Adapter\Db;
use Balloon\App\Notification\Adapter\Mail;
use Balloon\Server\User;
use Micro\Container\AdapterAwareInterface;
use Psr\Log\LoggerInterface;
use MongoDB\Database;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Cursor;

class Notifier implements AdapterAwareInterface
{
    /**
     * Default adapter.
     *
     * @var array
     */
    const DEFAULT_ADAPTER = [
        Mail::class => [],
        Db::class => [],
    ];

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
     * Database
     *
     * @var Database
     */
    protected $db;

    /**
     * Collection name
     *
     * @var string
     */
    protected $collection_name = 'notification';

    /**
     * Constructor.
     *
     * @param Database $db
     * @param LoggerInterace $logger
     * @param iterable       $config
     */
    public function __construct(Database $db, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->db = $db;
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
                        $this->injectAdapter($name, $adapter);
                    }

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Get default adapter.
     *
     * @return array
     */
    public function getDefaultAdapter(): array
    {
        return self::DEFAULT_ADAPTER;
    }

    /**
     * Send notification.
     *
     * @param array  $receiver
     * @param User   $sender
     * @param string $subject
     * @param string $body
     * @param array  $context
     *
     * @return bool
     */
    public function notify(array $receiver, ?User $sender, string $subject, string $body, array $context = []): bool
    {
        if (0 === count($this->adapter)) {
            $this->logger->warning('there are no notification adapter enabled, notification can not be sent', [
                'category' => get_class($this),
            ]);

            return false;
        }

        foreach ($this->adapter as $name => $adapter) {
            $this->logger->debug('send notification ['.$subject.'] via adpater ['.$name.']', [
                'category' => get_class($this),
            ]);

            $adapter->notify($receiver, $sender, $subject, $body, $context);
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
     *
     * @return AdapterInterface
     */
    public function injectAdapter($adapter, ?string $name = null): AdapterAwareInterface
    {
        if (!($adapter instanceof AdapterInterface)) {
            throw new Exception('adapter needs to implement AdapterInterface');
        }

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
    public function getAdapter(string $name)
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
     * @return array
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
     * Add notification
     *
     * @param array $receiver
     * @param User $user
     * @param string $subject
     * @param string $body
     * @param array $context
     * @return ObjectId
     */
    public function postNotification(array $receiver, ?User $sender, string $subject, string $body, array $context = []): ObjectId
    {
        $data = [
            'subject' => $subject,
            'body' => $body,
            'context' => $context,
            'receiver' => [],
        ];

        if($sender instanceof User) {
            $data['sender'] = $sender->getId();
        }

        foreach($receiver as $user) {
            $data['receiver'][] = $user->getId();
        }

        $result = $this->db->{$this->collection_name}->insertOne($data);
        return $result->getInsertedId();
    }


    /**
     * Get notifications
     *
     * @param User $user
     * @return Cursor
     */
    public function getNotifications(User $user): Cursor
    {
        $result = $this->db->{$this->collection_name}->find(['receiver' => $user->getId()]);
        return $result;
    }


    /**
     * Get notifications
     *
     * @param User $user
     * @param ObjectId $id
     * @return bool
     */
    public function deleteNotification(User $user, ObjectId $id): bool
    {
        $result = $this->db->{$this->collection_name}->findOne([
            '_id' => $id,
            'receiver' => $user->getId()
        ]);

        if($result === null) {
            throw new Exception('notification not found');
        }

        if(count($result['receiver']) <= 1) {
            $this->logger->debug('notification ['.$id.'] has only one member left, remove it', [
                'category' => get_class($this)
            ]);

            $result = $this->db->{$this->collection_name}->deleteOne(['_id' => $id]);
        } else {
            $this->logger->debug('notification ['.$id.'] has other members left, remove member ['.$user->getId().']', [
                'category' => get_class($this)
            ]);

            $result = $this->db->{$this->collection_name}->update([
                '_id' => $id,
                '$pull' => [
                    'receiver' => $user->getId()
                ]
            ]);

        }

        return true;
    }
}
