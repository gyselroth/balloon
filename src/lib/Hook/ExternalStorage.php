<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Async\SmbListener;
use Balloon\Async\SmbScanner;
use Balloon\Filesystem\Exception\NotFound as NotFoundException;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Storage\Exception;
use Balloon\Filesystem\Storage\Factory as StorageFactory;
use Balloon\Server;
use MongoDB\BSON\ObjectId;
use ParagonIE\Halite\HiddenString;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto as Symmetric;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use Psr\Log\LoggerInterface;
use TaskScheduler\JobInterface;
use TaskScheduler\Scheduler;

class ExternalStorage extends AbstractHook
{
    /**
     * Default key.
     */
    private const DEFAULT_KEY = '3140040033da9bd0dedd8babc8b89cda7f2132dd5009cc43c619382863d0c75e172ebf18e713e1987f35d6ea3ace43b561c50d9aefc4441a8c4418f6928a70e4655de5a9660cd323de63b4fd2fb76525470f25311c788c5e366e29bf60c438c4ac0b440e';

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Storage factory.
     *
     * @var StorageFactory
     */
    protected $factory;

    /**
     * Encryption key.
     *
     * @var EncryptionKey
     */
    protected $key;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Scheduler $scheduler, StorageFactory $factory, EncryptionKey $key, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->scheduler = $scheduler;
        $this->factory = $factory;
        $this->key = $key;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
        if (!isset($attributes['mount'])) {
            return;
        }

        if (isset($attributes['mount']['password'])) {
            if (KeyFactory::export($this->key)->getString() === self::DEFAULT_KEY) {
                throw new Exception\InvalidEncryptionKey('smb encryption key required to be changed');
            }

            $message = new HiddenString($attributes['mount']['password']);
            $attributes['mount']['password'] = Symmetric::encrypt($message, $this->key);
        }

        $this->factory->build($attributes['mount'])->test();
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        if (!$node->isMounted()) {
            return;
        }

        $this->addTasks($node->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if ($node->isMounted() && $force === true) {
            throw new \Exception('mounted collection can not get removed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (!$node->isMounted()) {
            return;
        }

        foreach ($this->scheduler->getJobs([
            '$or' => [['class' => SmbScanner::class], ['class' => SmbListener::class]],
            'data.id' => $node->getId(),
            'status' => ['$lte' => JobInterface::STATUS_PROCESSING],
        ]) as $job) {
            $this->scheduler->cancelJob($job->getId());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preExecuteAsyncJobs(): void
    {
        $fs = $this->server->getFilesystem();

        foreach ($this->scheduler->getJobs([
            '$or' => [['class' => SmbScanner::class], ['class' => SmbListener::class]],
            'status' => ['$lte' => JobInterface::STATUS_PROCESSING],
        ]) as $job) {
            try {
                if ($fs->findNodeById($job->getData()['id'])->isDeleted()) {
                    $this->scheduler->cancelJob($job->getId());
                }
            } catch (NotFoundException $e) {
                $this->scheduler->cancelJob($job->getId());
            } catch (\Exception $e) {
                $this->logger->error('failed pre check mount job ['.$job->getId().']', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        $nodes = $fs->findNodesByFilter([
            'deleted' => false,
            'directory' => true,
            'mount' => ['$type' => 3],
        ]);

        foreach ($nodes as $node) {
            $this->addTasks($node->getId());
        }
    }

    /**
     * Add tasks.
     */
    protected function addTasks(ObjectId $node): bool
    {
        $this->scheduler->addJobOnce(SmbScanner::class, [
            'id' => $node,
        ], [
            Scheduler::OPTION_INTERVAL => 86400,
        ]);

        $job = $this->scheduler->addJobOnce(SmbListener::class, [
            'id' => $node,
        ], [
            Scheduler::OPTION_IGNORE_MAX_CHILDREN => true,
            Scheduler::OPTION_RETRY => -1,
       ]);

        return true;
    }
}
