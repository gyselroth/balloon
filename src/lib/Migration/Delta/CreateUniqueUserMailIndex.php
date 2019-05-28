<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\Database;

class CreateUniqueUserMailIndex implements DeltaInterface
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
     * Change unique user mail index.
     */
    public function start(): bool
    {
        try {
            $this->createIndex();
        } catch (CommandException | RuntimeException $e) {
            if ($e->getCode() === 85) {
                $this->db->selectCollection('user')->dropIndex('mail_1');
                $this->createIndex();
            } else {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Create index.
     */
    protected function createIndex(): string
    {
        $this->db->user->createIndex([
            'mail' => 1,
        ], [
            'unique' => true,
        ]);
    }
}
