<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Plugin;

use Balloon\Filesystem;
use Balloon\Exception;
use Balloon\Filesystem\Node\File;
use Balloon\Queue\Preview as PreviewJob;

class Preview extends AbstractPlugin
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
        $queue = $node->getFilesystem()->getQueue();
        $queue->addJob(new PreviewJob([
            'id' => $node->getId()
        ]));
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
        $queue = $node->getFilesystem()->getQueue();
        $queue->addJob(new PreviewJob([
            'id' => $node->getId()
        ]));
    }
}
