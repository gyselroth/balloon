<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database\Delta;

use \MongoDB\Database;
use \Psr\Log\LoggerInterface;
use \Balloon\Async;
use \MongoDB\BSON\UTCDateTime;

class queueToCappedCollection extends AbstractDelta
{
    /**
     * Upgrade database
     *
     * @return bool
     */
    public function postObjects(): bool
    {
        $this->db->command([
            'convertToCapped' => 'queue'
            'size' => 100000
        ]);

        return true;
    }


    /**
     * Get collection
     *
     * @return string
     */
    public function getCollection(): string
    {
        return 'queue';
    }


    /**
     * Upgrade object
     *
     * @return array
     */
    public function upgradeObject(array $object): array
    {
        return [
            '$set' => [
                'timestamp' => new UTCDateTime(),
                'status'    => Async::STATUS_WAITING
            ]
        ];
    }
}
