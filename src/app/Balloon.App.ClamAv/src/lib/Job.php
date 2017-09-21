<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use \Psr\Log\LoggerInterface;
use \Micro\Config;
use \Balloon\Server;
use \MongoDB\Database;
use \Balloon\Async\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Run job
     *
     * @return bool
     */
    public function run(Filesystem $fs, LoggerInterface $logger, Config $config): bool
    {
        $file = $fs->findNodeWithId($this->data['id']);

        $logger->debug("scan file with clamav: [".$this->data['id']."]", [
            'category' => get_class($this),
        ]);

        $result = $this->server->getApp('Balloon.App.ClamAv')->scan($file);

        if ($result === 0) {
            $file->delete(true);
        }

        return true;
    }
}
