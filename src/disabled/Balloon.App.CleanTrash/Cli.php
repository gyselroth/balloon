<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CleanTrash;

use Balloon\App\AbstractApp;
use Balloon\App\AppInterface;
use MongoDB\BSON\UTCDateTime;

class Cli extends AbstractApp
{
    /**
     * max age.
     *
     * @var int
     */
    protected $max_age = 2592000;

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return AppInterface
     */
    public function setOptions(?Iterable $config = null): AppInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'max_age':
                    $this->max_age = (int) $value;

                break;
            }
        }

        return $this;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        $lt = time() - $this->max_age;

        $result = $this->server->getFilesystem()->findNodesWithCustomFilter(['deleted' => ['$lt' => new UTCDateTime($lt)]]);
        $this->logger->info('found ['.count($result).'] nodes for cleanup, force remove them from trash', [
            'category' => get_class($this),
        ]);

        foreach ($result as $node) {
            $node->delete(true);
        }

        return true;
    }
}
