<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Storage;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface as StorageAdapter;
use Balloon\Filesystem\Storage\Factory as StorageFactory;
use Balloon\Hook;
use Balloon\Server;
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
     * @var Storage
     */
    protected $storage;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Initialize.
     */
    public function __construct(Database $db, Hook $hook, LoggerInterface $logger, StorageAdapter $storage, Acl $acl, StorageFactory $storage_factory)
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
    public function build(Filesystem $fs, array $node): NodeInterface
    {
        if (!isset($node['directory'])) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        if (isset($node['storage_reference'])) {
            $external = $fs->findNodeById($node['storage_reference'])->getAttributes()['mount'];
            $storage = $this->storage_factory->build($external);
        } elseif (isset($node['mount'])) {
            $storage = $this->storage_factory->build($node['mount']);
        } else {
            $storage = $this->storage;
        }

        if (true === $node['directory']) {
            return new Collection($node, $fs, $this->logger, $this->hook, $this->acl, $storage);
        }

        return new File($node, $fs, $this->logger, $this->hook, $this->acl, $storage);
    }
}
