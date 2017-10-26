<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter\Basic;

use Micro\Auth\Adapter\AdapterInterface;
use Micro\Auth\Adapter\Basic\AbstractBasic;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Db extends AbstractBasic
{
    /**
     * Db.
     *
     * @var Database
     */
    protected $db;

    /**
     * Set options.
     *
     * @param Database $db
     *
     * @return AdapterInterface
     */
    public function __construct(LoggerInterface $logger, Database $db)
    {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Find identity.
     *
     * @param string $username
     *
     * @return array
     */
    public function findIdentity(string $username): ?array
    {
        return $this->db->user->findOne([
            'username' => $username,
        ]);
    }

    /**
     * Get attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
