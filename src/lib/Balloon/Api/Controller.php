<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api;

use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Server;

class Controller
{
    /**
     * Filesystem
     *
     * @var Filesystem
     */
    protected $fs;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    
    /**
     * Server
     *
     * @var Server
     */
    protected $server;

    
    /**
     * User
     *
     * @var User
     */
    protected $user;


    /**
     * Initialize
     *
     * @param  Filesystem $fs
     * @param  Config $config
     * @param  Logger $logger
     * @return void
     */
    public function __construct(Server $server, Logger $logger)
    {
        $this->fs     = $server->getFilesystem();
        $this->user   = $server->getUser();
        $this->server = $server;
        $this->logger = $logger;
    }
}
