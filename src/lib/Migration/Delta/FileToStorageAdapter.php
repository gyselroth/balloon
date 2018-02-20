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

class FileToStorageAdapter implements DeltaInterface
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

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
            'directory' => false,
            'file' => ['$exists' => true],
            'storage' => ['$exists' => false],
        ]);

        foreach ($cursor as $object) {
            if (isset($object['file'])) {
                $file = $object['file'];
            } else {
                $file = null;
            }

            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                    '$unset' => ['file' => 1],
                    '$set' => [
                        'storage_adapter' => 'gridfs',
                        'storage' => $file,
                    ],
                ]
            );
        }

        return true;
    }
}
