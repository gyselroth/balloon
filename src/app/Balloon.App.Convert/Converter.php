<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Converter as FileConverter;
use Balloon\Filesystem\Exception\NotFound as NotFoundException;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;

class Converter
{
    /**
     * Converter.
     *
     * @var FileConverter
     */
    protected $converter;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Collection name.
     *
     * @var string
     */
    protected $collection_name = 'convert';

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param Database      $db
     * @param Server        $server
     * @param FileConverter $converter
     * @param Async         $async
     * @param LoggerInterface   logger
     */
    public function __construct(Database $db, Server $server, FileConverter $converter, Async $async, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->db = $db;
        $this->converter = $converter;
        $this->async = $async;
        $this->logger = $logger;
    }

    /**
     * Get Supported formats.
     *
     * @param File $node
     *
     * @return array
     */
    public function getSupportedFormats(File $node): array
    {
        return $this->converter->getSupportedFormats($node);
    }

    /**
     * Get slaves.
     *
     * @param File $node
     * @param int  $offset
     * @param int  $limit
     * @param int  $total
     *
     * @return iterable
     */
    public function getSlaves(File $node, ?int $offset = null, ?int $limit = null, ?int &$total = null): Iterable
    {
        $total = $this->db->{$this->collection_name}->count([
            'master' => $node->getId(),
        ]);

        return $this->db->{$this->collection_name}->find([
            'master' => $node->getId(),
        ], [
            'skip' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Get slave.
     *
     * @param ObjectId $id
     *
     * @return array
     */
    public function getSlave(ObjectId $id): array
    {
        $result = $this->db->{$this->collection_name}->findOne([
            '_id' => $id,
        ]);

        if ($result === null) {
            throw new Exception\SlaveNotFound('slave not found');
        }

        return $result;
    }

    /**
     * Add slave.
     *
     * @param File   $node
     * @param string $format
     *
     * @return ObjectId
     */
    public function addSlave(File $node, string $format): ObjectId
    {
        $supported = $this->converter->getSupportedFormats($node);

        if (!in_array($format, $supported)) {
            throw new Exception\InvalidFormat('format '.$format.' is not available for file');
        }

        $result = $this->db->{$this->collection_name}->insertOne([
            'master' => $node->getId(),
            'format' => $format,
        ]);

        $this->async->addJob(Job::class, [
            'master' => $node->getId(),
        ]);

        return $result->getInsertedId();
    }

    /**
     * Delete slave.
     *
     * @param ObjectId $slave
     * @param bool     $node
     *
     * @return bool
     */
    public function deleteSlave(ObjectId $slave, bool $node = false): bool
    {
        $slave = $this->getSlave($slave);
        $result = $this->db->{$this->collection_name}->deleteOne([
            '_id' => $slave['_id'],
        ]);

        if (true === $node && isset($slave['slave'])) {
            $this->server->getFilesystem()->getNodeById($slave['slave'], File::class)->delete();
        }

        return $result->isAcknowledged();
    }

    /**
     * Convert master to slaves.
     *
     * @param ObjectId $slave
     * @param File     $master
     *
     * @return bool
     */
    public function convert(ObjectId $slave, File $master): bool
    {
        $this->logger->info('create slave for master node ['.$master->getId().']', [
            'category' => get_class($this),
        ]);

        $slave = $this->getSlave($slave);
        $result = $this->converter->convert($master, $slave['format']);
        $master->setFilesystem($this->server->getUserById($master->getOwner())->getFilesystem());

        try {
            if (isset($slave['slave'])) {
                $slave = $master->getFilesystem()->findNodeById($slave['slave']);

                $slave->setReadonly(false);
                $slave->put($result->getPath());
                $slave->setReadonly();

                return true;
            }
        } catch (NotFoundException $e) {
            $this->logger->debug('referenced slave node ['.$slave['slave'].'] does not exists or is not accessible', [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        }

        $this->logger->debug('create non existing slave ['.$slave['_id'].'] node for master ['.$master->getId().']', [
            'category' => get_class($this),
        ]);

        try {
            $name = substr($master->getName(), 0, (strlen($master->getExtension()) + 1) * -1);
            $name .= '.'.$slave['format'];
        } catch (\Exception $e) {
            $name = $master->getName().'.'.$slave['format'];
        }

        $node = $master->getParent()->addFile($name, $result->getPath(), [
            'owner' => $master->getOwner(),
            'app' => [
                __NAMESPACE__ => [
                    'master' => $slave['_id'],
                ],
            ],
        ], NodeInterface::CONFLICT_RENAME);

        $node->setReadonly();

        $result = $this->db->{$this->collection_name}->updateOne(['_id' => $slave['_id']], [
            '$set' => [
                'slave' => $node->getId(),
            ],
        ]);

        return $result->isAcknowledged();
    }
}
