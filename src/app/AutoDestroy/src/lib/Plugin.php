<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\AutoDestroy;

use \Balloon\Filesystem;
use \Balloon\Exception;
use \MongoDB\BSON\UTCDateTime;
use \Balloon\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    /**
     * Execute plugin
     *
     * @param   Filesystem $fs
     * @return  void
     */
    public function cli(Filesystem $fs): void
    {
        $result = $fs->findNodesWithCustomFilter(['destroy' => ['$lte' => new UTCDateTime()]]);
        foreach ($result as $node) {
            try {
                $node->delete(true);
            } catch (\Exception $e) {
                $this->logger->error('failed auto remove auto destroyable node', [
                    'category' => get_class($this),
                    'exception'=> $e
                ]);
            }
        }
    }
}
