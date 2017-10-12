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

use \GetOpt\GetOpt;

class Async extends AbstractConsole
{
    /**
     * Set options
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            \GetOpt\Option::create('q', 'queue'),
            \GetOpt\Option::create('d', 'daemon')
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
        if ($this->getopt->getOption('queue') === null) {
            $this->logger->debug("skip job queue execution", [
                'category' => get_class($this),
            ]);
        }

        if ($this->getopt->getOption('daemon') !== null) {
            $this->fireupDaemon();
        } else {
            if ($this->getopt->getOption('queue') !== null) {
                $cursor = $this->server->getAsync()->getCursor(false);
                $this->server->getAsync()->start($cursor, $this->server);
            }

            foreach ($this->server->getApp()->getApps() as $app) {
                $app->start();
            }
        }

        return true;
    }


    /**
     * Fire up daemon
     *
     * @return bool
     */
    protected function fireupDaemon(): bool
    {
        $this->logger->info("daemon execution requested, fire up daemon", [
            'category' => get_class($this),
        ]);

        $cursor = $this->server->getAsync()->getCursor(true);
        while (true) {
            if ($this->getopt->getOption('queue') !== null) {
                $this->server->getAsync()->start($cursor, $this->server);
            }
        }

        return true;
    }
}
