<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Storage;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Balloon\Filesystem\Storage\Factory as StorageFactory;
use Balloon\Hook;
use Balloon\Server;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Factory
{
    /**
     * Database.
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
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Storage.
     *
     * @var StorageAdapterInterface
     */
    protected $storage;

    /**
     * Storage Factory.
     *
     * @var StorageFactory
     */
    protected $storage_factory;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Storage cache.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Initialize.
     */
    public function __construct(Database $db, Hook $hook, LoggerInterface $logger, StorageAdapterInterface $storage, Acl $acl, StorageFactory $storage_factory)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->hook = $hook;
        $this->storage = $storage;
        $this->acl = $acl;
        $this->storage_factory = $storage_factory;
    }

    /**
     * Build node instance.
     */
    public function build(Filesystem $fs, array $node, ?Collection $parent): NodeInterface
    {
        if (!isset($node['directory'])) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        $storage = $this->storage;

        if (isset($node['reference'])) {
            $share = $fs->findRawNode($node['reference']);
            if (isset($share['mount'])) {
                $storage = $this->getStorage($share['_id'], $share['mount']);
            } elseif (isset($share['storage_reference'])) {
                $external = $fs->findRawNode($share['storage_reference'])['mount'];
                $storage = $this->getStorage($share['storage_reference'], $external);
            }
        } elseif (isset($node['storage_reference'])) {
            $external = $fs->findRawNode($node['storage_reference'])['mount'];
            $storage = $this->getStorage($node['storage_reference'], $external);
        } elseif (isset($node['mount'])) {
            $storage = $this->getStorage($node['_id'], $node['mount']);
        }

        if (true === $node['directory']) {
            return new Collection($node, $fs, $this->logger, $this->hook, $this->acl, $parent, $storage);
        }

        return new File($node, $fs, $this->logger, $this->hook, $this->acl, $parent);
    }

    /**
     * Get by id.
     */
    protected function getStorage(ObjectId $node, array $mount): StorageAdapterInterface
    {
        $id = (string) $node;

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        return $this->cache[$id] = $this->storage_factory->build($mount);
    }
}
