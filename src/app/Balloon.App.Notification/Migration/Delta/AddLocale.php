<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Migration\Delta;

use Balloon\Migration\Delta\DeltaInterface;
use MongoDB\Database;

class AddLocale implements DeltaInterface
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
        $this->db->selectCollection('notification')->updateMany(
            ['locale' => ['$exists' => false]],
            [
                '$set' => ['locale' => 'en_US'],
                '$unset' => ['context' => 1],
            ]
        );

        return true;
    }
}
