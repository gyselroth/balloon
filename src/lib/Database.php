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

use Balloon\Database\Core;
use Balloon\Database\DatabaseInterface;
use Balloon\Database\DeltaInterface;
use Balloon\Database\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use MongoDB\Database as MongoDB;

class Database
{
    /**
     * App
     *
     * @var App
     */
    protected $app;

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
     * Setup.
     *
     * @var array
     */
    protected $setup = [];

    /**
     * Progressbar.
     *
     * @var ProgressBar
     */
    protected $bar;

    /**
     * Construct.
     *
     * @param Server          $server
     * @param LoggerInterface $logger
     */
    public function __construct(App $app, MongoDB $db, LoggerInterface $logger, ?ProgressBar $bar = null)
    {
        $this->app = $app;
        $this->db = $db;
        $this->logger = $logger;
        $this->collect();
        $this->bar = $bar;
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

        foreach ($this->setup as $setup) {
            $this->logger->info('initialize database setup ['.get_class($setup).']', [
                'category' => get_class($this),
            ]);

            $setup->init();
        }

        $this->logger->info('initialization database setup completed', [
            'category' => get_class($this),
        ]);

        return true;
    }

    public function injectSetup(DatabaseInterface $db): Database
    {
        $this->setup[] = $db;
        return $this;
    }


    /**
     * Initialize database.
     *
     * @return Databse
     */
    public function collect(): Database
    {
        $this->setup[] = new Core($this->db, $this->logger);

        foreach ($this->app->getAppNamespaces() as $app => $namespace) {
            $class = $namespace.'Database';

            if (class_exists($class)) {
                $this->logger->debug('found database class ['.$class.'] from app ['.$app.']', [
                    'category' => get_class($this),
                ]);

                $db = new $class($this->db, $this->logger);
                if (!($db instanceof DatabaseInterface)) {
                    throw new Exception('database must include DatabaseInterface');
                }

                $this->setup[] = $db;
            } else {
                $this->logger->debug('no database class ['.$class.'] from app ['.$app.'] found', [
                    'category' => get_class($this),
                ]);
            }
        }

        return $this;
    }

    /**
     * Get setups.
     *
     * @return array
     */
    public function getSetups(): array
    {
        return $this->setup;
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
        return null !== $this->db->delta->findOne(['class' => $class]);
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

        foreach ($this->setup as $setup) {
            $deltas = $setup->getDeltas();
            $this->logger->info('found ['.count($deltas).'] deltas from ['.get_class($setup).']', [
                'category' => get_class($this),
            ]);

            foreach ($deltas as $delta) {
                if (false && $this->hasDelta($delta)) {
                    $this->logger->debug('skip already appended delta ['.$delta.']', [
                        'category' => get_class($this),
                    ]);
                } else {
                    $this->logger->info('apply database delta ['.$delta.']', [
                        'category' => get_class($this),
                    ]);

                    $delta = new $delta($this->db, $this->logger);
                    if (!($delta instanceof DeltaInterface)) {
                        throw new Exception('delta must include DeltaInterface');
                    }

                    $delta->preObjects();
                    $instances[] = $delta;
                }
            }
        }

        $this->upgradeObjects($instances);

        foreach ($instances as $delta) {
            $delta->postObjects();
            $this->db->delta->insertOne(['class' => get_class($delta)]);
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
        if (null !== $this->bar) {
            $count = 0;
            foreach ($this->getCollections() as $collection) {
                $count += $this->db->{$collection}->count();
            }
            $this->bar->setMaxStepts($count);
        }

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

                if (null !== $this->bar) {
                    $this->bar->advance();
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

        if (null !== $this->bar) {
            $this->bar->finish();
        }

        return true;
    }
}
