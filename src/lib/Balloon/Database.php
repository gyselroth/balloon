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

use \Balloon\Database\Exception;
use \Psr\Log\LoggerInterface;
use \Balloon\Database\Initialize;

class Database
{
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }


    public function init()
    {
        $this->logger->info('initialize mongodb', [
            'category' => get_class($this)
        ]);

        $collect = [
            new Initialize($this->server->getDatabase(), $this->logger)
        ];

        /*foreach($this->app as $app) {

        }*/


        $this->executeInit($collect);
    }

    protected function executeInit($collection)
    {
        foreach($collection as $init) {
            $init->init();
        }
    }

    public function upgrade()
    {
        $this->logger->info('upgrade mongodb', [
            'category' => get_class($this)
        ]);
    }
}
