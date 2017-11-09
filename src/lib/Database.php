<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Database\Delta\CoreInstallation;
use Balloon\Database\Delta\DeltaInterface;
use Balloon\Database\Exception;
use Psr\Log\LoggerInterface;
use MongoDB\Database as MongoDB;
use Micro\Container\AdapterAwareInterface;

class Database implements AdapterAwareInterface
{
    /**
     * Databse.
     *
     * @var Database
     */
    protected $db;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Deltas
     *
     * @var array
     */
    protected $delta = [];

    /**
     * Delta meta collection name
     *
     * @var string
     */
    protected $meta_collection = 'delta';

    /**
     * Construct.
     *
     * @param Server          $server
     * @param LoggerInterface $logger
     */
    public function __construct(MongoDB $db, LoggerInterface $logger, string $meta_collection='delta')
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->meta_collection = $meta_collection;
    }

    /**
     * Initialize database.
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->logger->info('initialize mongodb', [
            'category' => get_class($this),
        ]);

        if(count($this->delta) === 0) {
            $this->logger->warning('cannot initialize mongodb, no deltas have been applied', [
                'category' => get_class($this),
            ]);

            return false;
        }

        foreach ($this->delta as $name => $delta) {
            $this->logger->info('initialize database from delta ['.$name.']', [
                'category' => get_class($this),
            ]);

            $delta->init();
        }

        $this->logger->info('initialization database setup completed', [
            'category' => get_class($this),
        ]);

        return true;
    }


    /**
     * Check if delta was applied.
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasDelta(string $class): bool
    {
        return null !== $this->db->{$this->meta_collection}->findOne(['class' => $class]);
    }

    /**
     * Upgrade database.
     *
     * @return bool
     */
    public function upgrade(): bool
    {
        $this->logger->info('upgrade mongodb', [
            'category' => get_class($this),
        ]);

        $instances = [];

        if(count($this->delta) === 0) {
            $this->logger->warning('cannot upgrade mongodb, no deltas have been applied', [
                'category' => get_class($this),
            ]);

            return false;
        }

        foreach ($this->delta as $name => $delta) {
            if (false && $this->hasDelta($name)) {
                $this->logger->debug('skip existing delta ['.$name.']', [
                    'category' => get_class($this),
                ]);
            } else {
                $this->logger->info('apply database delta ['.$name.']', [
                    'category' => get_class($this),
                ]);

                $delta->preObjects();
                $instances[] = $delta;
            }
        }

        $this->upgradeObjects($instances);

        foreach ($instances as $delta) {
            $delta->postObjects();
            $this->db->{$this->meta_collection}->insertOne(['class' => get_class($delta)]);
        }

        $this->logger->info('executed database deltas successfully', [
            'category' => get_class($this),
        ]);

        return true;
    }

    /**
     * Get collections.
     *
     * @return array
     */
    public function getCollections(): array
    {
        $collections = [];
        foreach ($this->db->listCollections() as $collection) {
            $name = explode('.', $collection->getName());
            if ('system' !== array_shift($name)) {
                $collections[] = $collection->getName();
            }
        }

        return $collections;
    }

    /**
     * Upgrade objects.
     *
     * @return bool
     */
    public function upgradeObjects(array $deltas)
    {
        foreach ($this->getCollections() as $collection) {
            $this->logger->info('execute deltas for collection ['.$collection.']', [
                'category' => get_class($this),
            ]);

            foreach ($this->db->{$collection}->find() as $object) {
                $update = [];
                $this->logger->debug('find deltas for object ['.$object['_id'].'] from ['.$collection.']', [
                    'category' => get_class($this),
                ]);

                foreach ($deltas as $delta) {
                    if ($delta->getCollection() === $collection) {
                        $update = array_merge($update, $delta->upgradeObject($object));
                    }
                }

                if (0 === count($update)) {
                    $this->logger->debug('object ['.$object['_id'].'] from ['.$collection.'] does not need to be updated', [
                        'category' => get_class($this),
                    ]);
                } else {
                    $this->logger->debug('update object ['.$object['_id'].'] from ['.$collection.']', [
                        'category' => get_class($this),
                    ]);

                    $this->db->{$collection}->updateOne(['_id' => $object['_id']], $update);
                }
            }
        }

        return true;
    }


    /**
     * Get default adapter
     *
     * @return array
     */
    public function getDefaultAdapter(): array
    {
        return [];
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
        return isset($this->delta[$name]);
    }

    /**
     * Inject adapter.
     *
     * @param AdapterInterface $adapter
     *
     * @return AdapterInterface
     */
    public function injectAdapter($adapter, ?string $name=null): AdapterAwareInterface
    {
        if(!($adapter instanceof DeltaInterface)) {
            throw new Exception('delta needs to implement DeltaInterface');
        }

        if($name === null) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject delta ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this)
        ]);

        if ($this->hasAdapter($name)) {
            throw new Exception('delta '.$name.' is already registered');
        }

        $this->delta[$name] = $adapter;

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
            throw new Exception('delta '.$name.' is not registered');
        }

        return $this->delta[$name];
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
            return $this->delta;
        }
        $list = [];
        foreach ($adapter as $name) {
            if (!$this->hasAdapter($name)) {
                throw new Exception('delta '.$name.' is not registered');
            }
            $list[$name] = $this->delta[$name];
        }

        return $list;
    }

}
