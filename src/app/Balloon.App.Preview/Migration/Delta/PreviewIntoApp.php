<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Migration\Delta;

use Balloon\Migration\Delta\DeltaInterface;
use MongoDB\Database;

class PreviewIntoApp implements DeltaInterface
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
     * Upgrade object.
     *
     * @return bool
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
                    '$unset' => 'thumbnail',
                    '$set' => ['app.Balloon\App\Preview.preview' => $object['thumbnail']],
                ]
            );
        }

        return true;
    }
}
