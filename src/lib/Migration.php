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

use Balloon\Migration\Delta\DeltaInterface;
use Balloon\Migration\Exception;
use Micro\Container\AdapterAwareInterface;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Migration implements AdapterAwareInterface
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
     * Deltas.
     *
     * @var array
     */
    protected $delta = [];

    /**
     * Delta meta collection name.
     *
     * @var string
     */
    protected $meta_collection = 'delta';

    /**
     * Construct.
     *
     * @param Database        $db
     * @param LoggerInterface $logger
     * @param string          $meta_collection
     */
    public function __construct(Database $db, LoggerInterface $logger, string $meta_collection = 'delta')
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->meta_collection = $meta_collection;
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
     * Execute migration deltas.
     *
     * @param bool $force
     *
     * @return bool
     */
    public function start(bool $force = false): bool
    {
        $this->logger->info('execute migration deltas', [
            'category' => get_class($this),
        ]);

        $instances = [];

        if (0 === count($this->delta)) {
            $this->logger->warning('no deltas have been configured', [
                'category' => get_class($this),
            ]);

            return false;
        }

        foreach ($this->delta as $name => $delta) {
            if (false === $force && $this->hasDelta($name)) {
                $this->logger->debug('skip existing delta ['.$name.']', [
                    'category' => get_class($this),
                ]);
            } else {
                $this->logger->info('apply delta ['.$name.']', [
                    'category' => get_class($this),
                ]);

                $delta->start();
                $this->db->{$this->meta_collection}->insertOne(['class' => get_class($delta)]);
            }
        }

        $this->logger->info('executed migration deltas successfully', [
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
     * Get default adapter.
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
     * @param mixed $adapter
     *
     * @return AdapterAwareInterface
     */
    public function injectAdapter($adapter, ?string $name = null): AdapterAwareInterface
    {
        if (!($adapter instanceof DeltaInterface)) {
            throw new Exception('delta needs to implement DeltaInterface');
        }

        if (null === $name) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject delta ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this),
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
     * @return mixed
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
