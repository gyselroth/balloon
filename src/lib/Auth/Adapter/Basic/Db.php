<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter\Basic;

use Balloon\Auth\InternalAuthInterface;
use Micro\Auth\Adapter\Basic\AbstractBasic;
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
    public function __construct(LoggerInterface $logger, Database $db, ?iterable $config = null)
    {
        parent::__construct($logger);
        $this->db = $db;
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
        return $this->db->user->findOne([
            '$or' => [
                ['username' => $username],
                ['mail' => $username],
            ],
        ]);
    }

    /**
     * Get attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Auth.
     */
    public function plainAuth(string $username, string $password): bool
    {
        $result = $this->findIdentity($username);

        if (null === $result) {
            $this->logger->info('found no user named ['.$username.'] in database', [
                'category' => static::class,
            ]);

            return false;
        }

        if (!isset($result['password']) || empty($result['password'])) {
            $this->logger->info('found no password for ['.$username.'] in database', [
                'category' => static::class,
            ]);

            return false;
        }

        if (!password_verify($password, $result['password'])) {
            $this->logger->info('failed match given password for ['.$username.'] with stored hash in database', [
                'category' => static::class,
            ]);

            return false;
        }

        $this->attributes = $result;
        $this->identifier = $result['username'];

        return true;
    }
}
