<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Migration\Delta\DeltaInterface;
use Balloon\Migration\Exception;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Migration
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
    protected $meta_collection = 'migration';

    /**
     * Construct.
     *
     * @param Database        $db
     * @param LoggerInterface $logger
     * @param string          $meta_collection
     */
    public function __construct(Database $db, LoggerInterface $logger, string $meta_collection = 'migration')
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
    public function isDeltaApplied(string $class): bool
    {
        return null !== $this->db->{$this->meta_collection}->findOne(['class' => $class]);
    }

    /**
     * Execute migration deltas.
     *
     * @param bool $force
     * @param bool $ignore
     *
     * @return bool
     */
    public function start(bool $force = false, bool $ignore = false): bool
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
            if (false === $force && $this->isDeltaApplied($name)) {
                $this->logger->debug('skip existing delta ['.$name.']', [
                    'category' => get_class($this),
                ]);
            } else {
                $this->logger->info('apply delta ['.$name.']', [
                    'category' => get_class($this),
                ]);

                try {
                    $delta->start();
                    $this->db->{$this->meta_collection}->insertOne(['class' => get_class($delta)]);
                } catch (\Exception $e) {
                    $this->logger->error('failed to apply delta ['.get_class($delta).']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);

                    if ($ignore === false) {
                        throw $e;
                    }
                }
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
     * Has delta.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasDelta(string $name): bool
    {
        return isset($this->delta[$name]);
    }

    /**
     * Inject delta.
     *
     * @param DeltaInterface $delta
     *
     * @return Migration
     */
    public function injectDelta(DeltaInterface $delta, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($delta);
        }

        $this->logger->debug('inject delta ['.$name.'] of type ['.get_class($delta).']', [
            'category' => get_class($this),
        ]);

        if ($this->hasDelta($name)) {
            throw new Exception('delta '.$name.' is already registered');
        }

        $this->delta[$name] = $delta;

        return $this;
    }

    /**
     * Get delta.
     *
     * @param string $name
     *
     * @return DeltaInterface
     */
    public function getDelta(string $name): DeltaInterface
    {
        if (!$this->hasDelta($name)) {
            throw new Exception('delta '.$name.' is not registered');
        }

        return $this->delta[$name];
    }

    /**
     * Get deltas.
     *
     * @param array $deltas
     *
     * @return DeltaInterface[]
     */
    public function getDeltas(array $deltas = []): array
    {
        if (empty($delta)) {
            return $this->delta;
        }
        $list = [];
        foreach ($delta as $name) {
            if (!$this->hasDelta($name)) {
                throw new Exception('delta '.$name.' is not registered');
            }
            $list[$name] = $this->delta[$name];
        }

        return $list;
    }
}
