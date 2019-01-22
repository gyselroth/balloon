<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink\Migration\Delta;

use Balloon\Migration\Delta\DeltaInterface;
use MongoDB\Database;

class SharelinkIntoApp implements DeltaInterface
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Construct.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Upgrade object.
     *
     * @return array
     */
    public function start(): bool
    {
        $cursor = $this->db->storage->find(
            [
            'sharelink' => ['$exists' => 1], ]
        );

        foreach ($cursor as $object) {
            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                  '$unset' => ['sharelink' => 1],
                  '$set' => ['app.Balloon\App\Sharelink' => $object['sharelink']],
                ]
            );
        }

        return true;
    }
}
