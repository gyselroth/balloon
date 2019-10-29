<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
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
     * Collection name
     */
    public const COLLECTION_NAME = 'deltas';


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
     * Construct.
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Check if delta was applied.
     */
    public function isDeltaApplied(string $class): bool
    {
        return null !== $this->db->{self::COLLECTION_NAME}->findOne(['class' => $class]);
    }

    /**
     * Execute migration deltas.
     */
    public function start(bool $force = false, bool $ignore = false, array $deltas = []): bool
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

        foreach ($this->getDeltas($deltas) as $name => $delta) {
            if (false === $force && $this->isDeltaApplied($name)) {
                $this->logger->debug('skip existing delta ['.$name.']', [
                    'category' => get_class($this),
                ]);
            } else {
                $this->logger->info('apply delta ['.$name.']', [
                    'category' => get_class($this),
                ]);

                try {
                    $delta['delta']->start();
                    $this->db->{self::COLLECTION_NAME}->insertOne(['class' => $name]);
                } catch (\Exception $e) {
                    $this->logger->error('failed to apply delta ['.$name.']', [
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
     */
    public function injectDelta(DeltaInterface $delta, int $priority=0, ?string $name = null): self
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

        $this->delta[$name] = [
            'delta' => $delta,
            'priority' => $priority
        ];

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
     */
    public function getDeltas(array $deltas = []): array
    {
        $order = $this->delta;
        uasort($order, function($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return 0;
            }

            return ($a['priority'] < $b['priority']) ? 1 : -1;
        });

        if (empty($deltas)) {
            return $order;
        }

        $list = [];
        foreach ($order as $name) {
            if (!$this->hasDelta($name)) {
                throw new Exception('delta '.$name.' is not registered');
            }
            $list[$name] = $this->delta[$name];
        }

        return $list;
    }
}
