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
use \MongoDB\BSON\UTCDateTime;

class CleanTrash extends AbstractPlugin
{
    /**
     * max age
     *
     * @var int
     */
    protected $max_age = 2592000;


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return PluginInterface
     */
    public function setOptions(?Iterable $config): PluginInterface
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'max_age':
                    $this->max_age = (int)$value;
                break;
            }
        }

        return $this;
    }


    /**
     * Execute plugin
     *
     * @param   Filesystem $fs
     * @return  void
     */
    public function cli(Filesystem $fs): void
    {
        $lt  = time() - $this->max_age;

        $result = $fs->findNodesWithCustomFilter(['deleted' => ['$lt' => new UTCDateTime($lt)]]);
        $this->logger->info('found ['.count($result).'] nodes for cleanup, force remove them from trash', [
            'category' => get_class($this)
        ]);

        foreach ($result as $node) {
            $node->delete(true);
        }
    }
}
