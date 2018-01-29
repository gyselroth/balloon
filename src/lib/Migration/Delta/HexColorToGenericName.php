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
        '#ff4d8d' => 'magenta',
        '#854dff' => 'purple',
        '#4dc9ff' => 'blue',
        '#4dff88' => 'green',
        '#ffe14d' => 'yellow',
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
            $color = strtolower($object['meta']['color']):

            if (isset($this->map[$color])) {
                $this->db->storage->updateOne(
                    ['_id' => $object['_id']],
                    [
                        '$set' => ['meta.color' => $this->map[$color]],
                    ]
                );
            }
        }

        return true;
    }
}
