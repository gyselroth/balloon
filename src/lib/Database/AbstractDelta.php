<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database;

use MongoDB\Database;
use Psr\Log\LoggerInterface;

abstract class AbstractDelta implements DeltaInterface
{
    /**
     * Construct.
     *
     * @param Database        $db
     * @param LoggerInterface $logger
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Upgrade object.
     *
     * @param array $object
     *
     * @return array
     */
    public function upgradeObject(array $object): array
    {
        return [];
    }

    /**
     * Pre objects.
     *
     * @return bool
     */
    public function preObjects(): bool
    {
        return true;
    }

    /**
     * Post objects.
     *
     * @return bool
     */
    public function postObjects(): bool
    {
        return true;
    }
}
