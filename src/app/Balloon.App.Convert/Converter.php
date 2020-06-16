<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Converter as FileConverter;
use Balloon\Filesystem\Exception\NotFound as NotFoundException;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Session\Factory as SessionFactory;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use TaskScheduler\Scheduler;

class Converter
{
    /**
     * Converter.
     *
     * @var FileConverter
     */
    protected $converter;

    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

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
     * Session factory.
     *
     * @var SessionFactory
     */
    protected $session_factory;

    /**
     * Constructor.
     */
    public function __construct(Database $db, Server $server, FileConverter $converter, Scheduler $scheduler, SessionFactory $session_factory, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->db = $db;
        $this->converter = $converter;
        $this->scheduler = $scheduler;
        $this->session_factory = $session_factory;
        $this->logger = $logger;
    }

    /**
     * Get Supported formats.
     */
    public function getSupportedFormats(File $node): array
    {
        return $this->converter->getSupportedFormats($node);
    }

    /**
     * Get slaves.
     */
    public function getSlaves(File $node, array $query = [], ?int $offset = null, ?int $limit = null, ?int &$total = null): iterable
    {
        $filter = [
            'master' => $node->getId(),
        ];

        if (!empty($query)) {
            $filter = [
                '$and' => [
                    $filter,
                    $query,
                ],
            ];
        }

        $total = $this->db->{$this->collection_name}->count($filter);

        return $this->db->{$this->collection_name}->find($filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Get slave.
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

        $this->scheduler->addJob(Job::class, [
            'master' => $node->getId(),
        ]);

        return $result->getInsertedId();
    }

    /**
     * Delete slave.
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
                $session = $this->session_factory->add($this->server->getUserById($master->getOwner()), $slave->getParent(), $result);
                $slave->setReadonly(false);
                $slave->setContent($session);
                fclose($result);
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

        $session = $this->session_factory->add($this->server->getUserById($master->getOwner()), $master->getParent(), $result);
        $node = $master->getParent()->addFile($name, $session, [
            'owner' => $master->getOwner(),
            'app' => [
                __NAMESPACE__ => [
                    'master' => $slave['_id'],
                ],
            ],
        ], NodeInterface::CONFLICT_RENAME);

        fclose($result);
        $node->setReadonly();

        $result = $this->db->{$this->collection_name}->updateOne(['_id' => $slave['_id']], [
            '$set' => [
                'slave' => $node->getId(),
            ],
        ]);

        return $result->isAcknowledged();
    }
}
