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

use Balloon\App\AbstractApp;
use Micro\Container\AdapterAwareInterface;
use Balloon\App\Notification\Adapter\AdapterInterface;
use Balloon\App\Notification\Hook\NewShareAdded;
use Psr\Log\LoggerInterface;
use Balloon\App\Notification\Adapter\Mail;
use Balloon\App\Notification\Adapter\Db;

class Notifier implements AdapterAwareInterface
{
    /**
     * Notifications.
     *
     * @var array
     */
    protected $notifications = [];


    /**
     * Adapter
     *
     * @var array
     */
    protected $adapter = [];


    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Default adapter
     *
     * @var array
     */
    const DEFAULT_ADAPTER = [
        Mail::class => [],
        Db::class => []
    ];

    /**
     * Constructor
     *
     * @param LoggerInterace $logger
     * @param Iterable $config
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config=null)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }


    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return App
     */
    public function setOptions(?Iterable $config = null): App
    {
        if (null === $config) {
            return $this;
        }

        foreach($config as $adapter) {
            $this->injectAdapter($adapter);
        }

        return $this;
    }


    /**
     * Get default adapter
     *
     * @return array
     */
    public function getDefaultAdapter(): array
    {
        return self::DEFAULT_ADAPTER;
    }


    /**
     * Send notification
     *
     * @param array $user
     * @param string $subject
     * @param string $body
     * @param array $context
     * @return bool
     */
    public function notify(array $user, string $subject, string $body, array $context=[]): bool
    {
        if(count($this->adapter) === 0) {
            $this->logger->warning('there are no notification adapter enabled, notification can not be sent', [
                'category' => get_class($this)
            ]);

            return false;
        }

        foreach($this->adapter as $name => $adapter) {
            $this->logger->debug('send notification ['.$subject.'] via adpater ['.$name.']', [
                'category' => get_class($this)
            ]);

            $adapter->notify($user, $subject, $body, $context);
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
     * Inject adapter
     *
     * @param AdapterInterface $adapter
     *
     * @return AdapterInterface
     */
    public function injectAdapter(string $name, AdapterInterface $adapter): AdapterInterface
    {
        $this->logger->debug('inject notification adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this)
        ]);

        if ($this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is already registered');
        }

        $this->adapter[$name] = $adapter;
        return $adapter;
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
}
