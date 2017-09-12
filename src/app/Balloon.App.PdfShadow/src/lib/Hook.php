<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\PdfShadow;

use \Balloon\Filesystem;
use \Balloon\Exception;
use \Balloon\Filesystem\Node\File;
use \Balloon\Hook\AbstractHook;

class Hook extends AbstractHook
{
    /**
     * Run: postPutFile
     *
     * Executed post a put file request
     *
     * @param   File $node
     * @param   string|resource $content
     * @param   bool $force
     * @param   array $attributes
     * @return  void
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
        if(count($node->getAppAttributes($node->getFilesystem()->getServer()->getApp()->getApp('Balloon.App.PdfShadow'))) !== 0) {
            $queue = $node->getFilesystem()->getServer()->getAsync();
            $queue->addJob(new Job([
                'id' => $node->getId()
            ]));
        }
    }
  
  
    /**
     * Run: postRestoreFile
     *
     * Executed post version rollback
     *
     * @param   File $node
     * @param   int $version
     * @return  void
     */
    public function postRestoreFile(File $node, int $version): void
    {
        if(count($node->getAppAttributes($node->getFilesystem()->getServer()->getApp()->getApp('Balloon.App.PdfShadow'))) !== 0) {
            $queue = $node->getFilesystem()->getServer()->getAsync();
            $queue->addJob(new Job([
                'id' => $node->getId()
            ]));
        }
    }
}
