<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use \Psr\Log\LoggerInterface;
use \Balloon\Server;

interface JobInterface
{
    /**
     * Get job data
     *
     * @return array
     */
    public function getData(): array;


    /**
     * Start job
     *
     * @param   Server $server
     * @param   LoggerInterface $logger
     * @return  bool
     */
    public function start(Server $server, LoggerInterface $logger): bool;
}
