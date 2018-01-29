<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\Database;

class HexColorToGenericName implements DeltaInterface
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Map.
     *
     * @var array
     */
    protected $map = [
        '#FF4D8D' => 'magenta',
        '#854DFF' => 'purple',
        '#4DC9FF' => 'blue',
        '#4DFF88' => 'green',
        '#FFE14D' => 'yellow',
    ];

    /**
     * Construct.
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        $cursor = $this->db->storage->find([
            'meta.color' => ['$exists' => 1],
        ]);

        foreach ($cursor as $object) {
            if (isset($this->map[$object['meta']['color']])) {
                $this->db->storage->updateOne(
                    ['_id' => $object['_id']],
                    [
                        '$set' => ['meta.color' => $object['meta']['color']],
                    ]
                );
            } else {
                $this->db->storage->updateOne(
                    ['_id' => $object['_id']],
                    [
                        '$unset' => ['meta.color'],
                    ]
                );
            }
        }

        return true;
    }
}
