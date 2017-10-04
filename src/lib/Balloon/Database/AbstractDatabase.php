<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database;

use \MongoDB\Database;
use \Psr\Log\LoggerInterface;

abstract class AbstractDatabase implements DatabaseInterface
{
    /**
     * Construct
     *
     * @param Database $db
     * @param LoggerInterface $logger
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }


    /**
     * Initialize database
     *
     * @return bool
     */
    public function init(): bool
    {
        return true;
    }


    /**
     * Get deltas
     *
     * @return array
     */
    public function getDeltas(): array
    {
        return [];
    }
}
