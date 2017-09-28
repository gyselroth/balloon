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

use \Balloon\Upgrade\Exception;

class Database
{
    public function __construct(Database $db, App $app, Logger $logger)
    {
        $this->db = $db;
        $this->app = $app;
        $this->logger = $logger;
    }


    public function init()
    {
        $this->logger->info('initialize mongodb', [
            'category' => get_class($this)
        ]),

        $collect = [
            new Initialize($this->db, $this->logger);
        ];

        /*foreach($this->app as $app) {

        }*/
    }

    protected function executeInit()
    {
        foreach($collection as $init) {
            //do smth with init
        }
    }

    public function upgrade()
    {
        $this->logger->info('upgrade mongodb', [
            'category' => get_class($this)
        ]),
    }
}
