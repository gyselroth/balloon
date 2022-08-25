<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Migration\Delta\DeltaInterface;
use Balloon\Migration\Exception;
use MongoDB\Database;
use MongoDB\Driver\Exception\ServerException;
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
     */
    public function __construct(Database $db, LoggerInterface $logger, string $meta_collection = 'migration')
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->meta_collection = $meta_collection;
    }

    /**
     * Check if delta was applied.
     */
    public function isDeltaApplied(string $class): bool
    {
        try {
            return null !== $this->db->{$this->meta_collection}->findOne(['class' => $class]);
        } catch (ServerException $e) {
            if ($e->getCode() === 13436) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Execute migration deltas.
     */
    public function start(bool $force = false, bool $ignore = false, array $deltas = []): bool
    {
        $this->logger->info('execute migration deltas', [
            'category' => static::class,
        ]);

        $instances = [];

        if (0 === count($this->delta)) {
            $this->logger->warning('no deltas have been configured', [
                'category' => static::class,
            ]);

            return false;
        }

        foreach (array_reverse($this->getDeltas($deltas)) as $name => $delta) {
            if (false === $force && $this->isDeltaApplied($name)) {
                $this->logger->debug('skip existing delta ['.$name.']', [
                    'category' => static::class,
                ]);
            } else {
                $this->logger->info('apply delta ['.$name.']', [
                    'category' => static::class,
                ]);

                try {
                    $delta->start();
                    $this->db->{$this->meta_collection}->insertOne(['class' => get_class($delta)]);
                } catch (\Exception $e) {
                    $this->logger->error('failed to apply delta ['.get_class($delta).']', [
                        'category' => static::class,
                        'exception' => $e,
                    ]);

                    if ($ignore === false) {
                        throw $e;
                    }
                }
            }
        }

        $this->logger->info('executed migration deltas successfully', [
            'category' => static::class,
        ]);

        return true;
    }

    /**
     * Get collections.
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
     */
    public function hasDelta(string $name): bool
    {
        return isset($this->delta[$name]);
    }

    /**
     * Inject delta.
     *
     * @return Migration
     */
    public function injectDelta(DeltaInterface $delta, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($delta);
        }

        $this->logger->debug('inject delta ['.$name.'] of type ['.get_class($delta).']', [
            'category' => static::class,
        ]);

        if ($this->hasDelta($name)) {
            throw new Exception('delta '.$name.' is already registered');
        }

        $this->delta[$name] = $delta;

        return $this;
    }

    /**
     * Get delta.
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
     * @return DeltaInterface[]
     */
    public function getDeltas(array $deltas = []): array
    {
        if (empty($deltas)) {
            return $this->delta;
        }
        $list = [];
        foreach ($deltas as $name) {
            if (!$this->hasDelta($name)) {
                throw new Exception('delta '.$name.' is not registered');
            }
            $list[$name] = $this->delta[$name];
        }

        return $list;
    }
}
