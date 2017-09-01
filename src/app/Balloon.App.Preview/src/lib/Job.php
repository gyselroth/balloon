<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Server;
use \Balloon\Async\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Run job
     *
     * @return bool
     */
    public function run(Server $server, Logger $logger): bool
    {
        $file = $server->getFilesystem()->findNodeWithId($this->data['id']);

        $logger->info("create preview for [".$this->data['id']."]", [
            'category' => get_class($this),
        ]);

        
        $content = $server->getApp()
            ->getApp('Balloon.App.Preview')
            ->getConverter()
            ->create($file);

        $file->setPreview($content);
        return true;
    }
}
