<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Storage;

use Balloon\App\Idp\Exception\MultiFactorAuthenticationRequired;
use Balloon\Hook;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Auth\Adapter\Basic\BasicInterface;
use Micro\Auth\Auth;
use MongoDB\Database;
use OAuth2\Storage\MongoDB as OAuthMongoDB;
use Psr\Log\LoggerInterface;

class Db extends OAuthMongoDB
{
    /**
     * Auth.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * {@inheritdoc}
     */
    public function __construct(Database $db, Auth $auth, Hook $hook, Server $server, LoggerInterface $logger, array $config = [])
    {
        $this->server = $server;
        $this->auth = $auth;
        $this->hook = $hook;
        $this->logger = $logger;

        parent::__construct($db, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function checkUserCredentials($username, $password)
    {
        foreach ($this->auth->getAdapters() as $adapter) {
            if ($adapter instanceof BasicInterface) {
                try {
                    if ($adapter->plainAuth($username, $password) === true) {
                        $user = null;
                        $identity = $this->auth->createIdentity($adapter);

                        try {
                            $user = $this->server->getUserByName($username);
                        } catch (User\Exception\NotFound $e) {
                            $this->logger->warning('failed connect authenticated user, user account does not exists', [
                                'category' => get_class($this),
                            ]);
                        }

                        $this->hook->run('preServerIdentity', [$identity, &$user]);

                        if (!($user instanceof User)) {
                            throw new User\Exception\NotAuthenticated('user does not exists', User\Exception\NotAuthenticated::USER_NOT_FOUND);
                        }

                        if ($user->isDeleted()) {
                            throw new User\Exception\NotAuthenticated(
                                'user is disabled and can not be used',
                                User\Exception\NotAuthenticated::USER_DELETED
                            );
                        }

                        return true;
                    }
                } catch (MultiFactorAuthenticationRequired $e) {
                    throw $e;
                } catch (\Exception $e) {
                    $this->logger->error('failed authenticate user, unexcepted exception was thrown', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($username)
    {
        try {
            return $this->server->getUserByName($username)
                ->getAttributes();
        } catch (User\Exception\NotFound $e) {
            $this->logger->warning('failed connect authenticated user, user account does not exists', [
                'category' => get_class($this),
            ]);

            return false;
        }
    }
}
