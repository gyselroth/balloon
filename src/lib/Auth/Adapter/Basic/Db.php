<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
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
    public function __construct(LoggerInterface $logger, Database $db, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->db = $db;
        $this->setOptions($config);
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

    /**
     * Auth.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function plainAuth(string $username, string $password): bool
    {
        $result = $this->findIdentity($username);

        if (null === $result) {
            $this->logger->info('found no user named ['.$username.'] in database', [
                'category' => get_class($this),
            ]);

            return false;
        }

        if (!isset($result['password']) || empty($result['password'])) {
            $this->logger->info('found no password for ['.$username.'] in database', [
                'category' => get_class($this),
            ]);

            return false;
        }

        if (!password_verify($password, $result['password'])) {
            $this->logger->info('failed match given password for ['.$username.'] with stored hash in database', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $this->attributes = $result;
        $this->identifier = $username;

        return true;
    }
}
