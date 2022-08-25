<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Server;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class AutoDestroy extends AbstractJob
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
        $result = $this->server->getFilesystem()->findNodesByFilter(['destroy' => ['$lte' => new UTCDateTime()]]);
        foreach ($result as $node) {
            try {
                $node->delete(true);
            } catch (\Exception $e) {
                $this->logger->error('failed auto remove auto destroyable node', [
                    'category' => static::class,
                    'exception' => $e,
                ]);
            }
        }

        return true;
    }
}
