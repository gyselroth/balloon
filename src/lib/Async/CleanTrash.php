<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Async\AbstractJob;
use Balloon\App\AppInterface;
use MongoDB\BSON\UTCDateTime;
use Balloon\Server;
use Psr\Log\LoggerInterface;

class CleanTrash extends AbstractJob
{
    /**
     * Server
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Default data
     *
     * @var array
     */
    protected $data = [
        'max_age' => 86400,
    ];

    /**
     * Constructor
     *
     * @param Server $server
     * @param LoggerInterface $logger
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }


    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
var_dump($this->data);
        $lt = time() - $this->data['max_age'];

        $result = $this->server->getFilesystem()->findNodesWithCustomFilter(['deleted' => ['$lt' => new UTCDateTime($lt)]]);
        $this->logger->info('found ['.count($result).'] nodes for cleanup, force remove them from trash', [
            'category' => get_class($this),
        ]);

        foreach ($result as $node) {
            $node->delete(true);
        }

        return true;
    }
}
