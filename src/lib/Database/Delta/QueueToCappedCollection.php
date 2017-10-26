<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database\Delta;

use Balloon\Async;
use Balloon\Database\AbstractDelta;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Exception\RuntimeException;

class queueToCappedCollection extends AbstractDelta
{
    /**
     * Upgrade database.
     *
     * @return bool
     */
    public function postObjects(): bool
    {
        try {
            $this->db->command([
                'convertToCapped' => 'queue',
                'size' => 100000,
            ]);
        } catch (RuntimeException $e) {
            if (26 === $e->getCode()) {
                $this->logger->debug('queue collection does not exists, skip upgrade', [
                    'category' => get_class($this),
                ]);
            } else {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Get collection.
     *
     * @return string
     */
    public function getCollection(): string
    {
        return 'queue';
    }

    /**
     * Upgrade object.
     *
     * @return array
     */
    public function upgradeObject(array $object): array
    {
        return [
            '$set' => [
                'timestamp' => new UTCDateTime(),
                'status' => Async::STATUS_WAITING,
            ],
        ];
    }
}
