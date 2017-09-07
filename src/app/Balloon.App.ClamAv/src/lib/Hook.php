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

use \Balloon\User;
use \Balloon\Queue\JobInterface;
use \Balloon\Filesystem\Node\NodeInterface;
use \Balloon\Filesystem\Node\File;
use \Balloon\Resource;
use \Balloon\Filesystem\Node\Collection;
use \Balloon\Queue\Mail;
use \Zend\Mail\Message;
use \Balloon\Plugin\AbstractPlugin;
use \Balloon\Plugin\PluginInterface;

class Plugin extends AbstractPlugin
{
    /**
     * Run: postPutFile
     *
     * Executed pre a put file request
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
        $queue->addJob(new Job([
            'id' => $node->getId()
        ]));
    }
}
