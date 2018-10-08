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

class GridfsFlatReferences implements DeltaInterface
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
     * Start.
     */
    public function start(): bool
    {
        $cursor = $this->db->{'fs.files'}->find([
            'metadata.ref' => ['$exists' => 1],
        ]);

        foreach ($cursor as $object) {
            $references = [];
            foreach ($object['metadata']['ref'] as $reference) {
                $references[] = $reference['id'];
            }

            $this->db->{'fs.files'}->updateOne(
                ['_id' => $object['_id']],
                [
                    '$unset' => [
                        'metadata.ref' => 1,
                        'metadata.share_ref' => 1,
                    ],
                    '$set' => [
                        'metadata.references' => $references,
                    ],
                ]
            );
        }

        return true;
    }
}
