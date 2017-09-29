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

use \Balloon\Database as BalloonDatabase;

class Database extends AbstractConsole
{
    /**
     * Set options
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            \GetOpt\Option::create('i', 'init'),
            \GetOpt\Option::create('u', 'upgrade')
        ]);

        return $this;
    }


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        $db = new BalloonDatabase($this->server, $this->logger);
        if($this->getopt->getOption('init') !== null) {
            return $db->init();
        } elseif($this->getopt->getOption('upgrade') !== null) {
            return $db->upgrade();
        }

        return false;
    }
}
