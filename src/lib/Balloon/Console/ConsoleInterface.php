<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use \Psr\Log\LoggerInterface;
use \Balloon\Server;
use \GetOpt\GetOpt;

interface ConsoleInterface
{
    /**
     * Init
     *
     * @param  Server $server
     * @param  LoggerInterface $logger
     * @param  GetOpt $getopt
     * @return void
     */
    public function __construct(Server $server, LoggerInterface $logger, GetOpt $getopt);


    /**
     * Parse cmd options
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface;


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool;
}
