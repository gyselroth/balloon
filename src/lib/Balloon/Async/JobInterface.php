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

use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Server;

interface JobInterface
{
    /**
     * Run job
     *
     * @param   Filesystem $fs
     * @return  bool
     */
    public function getData(): array;


    /**
     * Run job
     *
     * @param   Server $server
     * @param   Logger $logger
     * @return  bool
     */
    public function run(Server $server, Logger $logger): bool;
}
