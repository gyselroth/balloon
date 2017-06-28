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
use \Micro\Config;
use \Balloon\Filesystem;
use \MongoDB\Database;
use \Balloon\App\Preview\Converter;
use \Balloon\Queue\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Run job
     *
     * @return bool
     */
    public function run(Filesystem $fs, Logger $logger, Config $config): bool
    {
        $file = $fs->findNodeWithId($this->data['id']);

        $logger->info("create preview for [".$this->data['id']."]", [
            'category' => get_class($this),
        ]);

        $preview = new Converter($logger, $config->plugins->preview->config);
        $content = $preview->create($file);
        $file->setPreview($content);
        return true;
    }
}
