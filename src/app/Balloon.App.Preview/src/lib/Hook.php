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

use \Balloon\Filesystem;
use \Balloon\Exception;
use \Balloon\Filesystem\Node\File;
use \Balloon\Hook\AbstractHook;
use \MongoDB\GridFS\Exception\FileNotFoundException;

class Hook extends AbstractHook
{
    /**
     * Run: preDeleteFile
     *
     * Executed pre a file gets deleted
     *
     * @param   File $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if ($force === true) {
            try {
                $node->getFilesystem()->getServer()->getApp()->getApp('Balloon.App.Preview')->deletePreview($node);
            } catch (FileNotFoundException $e) {
                $this->logger->debug('could not remove preview from file ['.$node->getId().'], preview does not exists', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }
    }


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
        $queue = $node->getFilesystem()->getServer()->getAsync();
        $queue->addJob(new Job([
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
        $queue = $node->getFilesystem()->getServer()->getAsync();
        $queue->addJob(new Job([
            'id' => $node->getId()
        ]));
    }
}
