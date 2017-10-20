<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\AutoDestroy;

use Balloon\App\AbstractApp;
use Balloon\Exception;
use MongoDB\BSON\UTCDateTime;

class Cli extends AbstractApp
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
     *
     * @param Router $router
     * @param Hook   $hook
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * Start.
     */
    public function start(): bool
    {
        $result = $this->server->getFilesystem()->findNodesWithCustomFilter(['destroy' => ['$lte' => new UTCDateTime()]]);
        foreach ($result as $node) {
            try {
                $node->delete(true);
            } catch (\Exception $e) {
                $this->logger->error('failed auto remove auto destroyable node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return true;
    }
}
