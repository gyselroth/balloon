<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \Psr\Log\LoggerInterface as Logger;

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
     * Config
     *
     * @var Config
     */
    protected $config;

    
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
    public function __construct(Filesystem $fs, Config $config, Logger $logger)
    {
        $this->fs     = $fs;
        $this->config = $config;
        $this->user   = $fs->getUser();
        $this->logger = $logger;
    }
}
