<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter\Basic;

use Balloon\Auth\InternalAuthInterface;
use Micro\Auth\Adapter\Basic\AbstractBasic;
use Micro\Auth\IdentityInterface;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Db extends AbstractBasic implements InternalAuthInterface
{
    /**
     * Db.
     *
     * @var Database
     */
    protected $db;

    /**
     * Set options.
     */
    public function __construct(LoggerInterface $logger, Database $db, array $config = [])
    {
        parent::__construct($logger);
        $this->db = $db;
        $this->identity_attribute = 'name';
        $this->setOptions($config);
    }

    /**
     * {@inheritdoc}
     */
    public function isInternal(): bool
    {
        return true;
    }

    /**
     * Find identity.
     */
    public function findIdentity(string $username): ?array
    {
        return $this->db->users->findOne([
            '$or' => [
                ['data.username' => $username],
                ['data.mail' => $username],
            ],
        ]);
    }

    /**
     * Get attributes.
     */
    public function getAttributes(IdentityInterface $identity): array
    {
        return [];
    }

    /**
     * Auth.
     */
    public function plainAuth(string $username, string $password): ?array
    {
        $result = $this->findIdentity($username);

        if (null === $result) {
            $this->logger->info('found no user named ['.$username.'] in database', [
                'category' => get_class($this),
            ]);

            return null;
        }

        if (!isset($result['hash']) || empty($result['hash'])) {
            $this->logger->info('found no password for ['.$username.'] in database', [
                'category' => get_class($this),
            ]);

            return null;
        }

        if (!password_verify($password, $result['hash'])) {
            $this->logger->info('failed match given password for ['.$username.'] with stored hash in database', [
                'category' => get_class($this),
            ]);

            return null;
        }

        return $result;
    }
}
