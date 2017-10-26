<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Async\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Run job.
     *
     * @return bool
     */
    public function start(): bool
    {
        $file = $server->getFilesystem()->findNodeWithId($this->data['id']);

        $logger->debug('scan file with clamav: ['.$this->data['id'].']', [
            'category' => get_class($this),
        ]);

        try {
            $result = $server->getApp()->getApp('Balloon.App.ClamAv')->scan($file);
        } catch (Exception $e) {
            $logger->error($e->getMessage(), [
              'category' => get_class($this),
          ]);

            return false;
        }

        $infected = Cli::FILE_INFECTED === $result;

        $server->getApp()->getApp('Balloon.App.ClamAv')->handleFile($file, $infected);

        return true;
    }
}
