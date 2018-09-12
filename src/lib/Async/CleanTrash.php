<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Server;
use Exception;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class CleanTrash extends AbstractJob
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Default data.
     *
     * @var array
     */
    protected $data = [
        'max_age' => 5184000,
    ];

    /**
     * Constructor.
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $lt = (time() - $this->data['max_age']) * 1000;
        $result = $this->server->getFilesystem()->findNodesByFilter([
            'reference' => ['$exists' => false],
            'deleted' => ['$lt' => new UTCDateTime($lt)],
        ]);

        foreach ($result as $node) {
            try {
                $node->delete(true);
            } catch (Exception $e) {
                $this->logger->error('failed delete node ['.$node->getId().']', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        $this->logger->info('found ['.$result->getReturn().'] nodes for cleanup, force removed them from trash', [
            'category' => get_class($this),
        ]);

        return true;
    }
}
