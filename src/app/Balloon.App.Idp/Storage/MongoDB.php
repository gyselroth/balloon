<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Storage;

use Balloon\Hook;
use Balloon\Server\User;
use Micro\Auth\Adapter\Basic\BasicInterface;
use Micro\Auth\Auth;
use MongoDB\Database;
use OAuth2\Storage\MongoDB as OAuthMongoDB;

class MongoDB extends OAuthMongoDB
{
    /**
     * Auth.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Adapter.
     *
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * {@inheritdoc}
     */
    public function __construct(Database $db, Auth $auth, Hook $hook, array $config = [])
    {
        $this->auth = $auth;
        $this->server = $server;
        $this->hook = $hook;

        parent::__construct($db, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function checkUserCredentials($username, $password)
    {
        foreach ($this->auth->getAdapters() as $adapter) {
            if ($adapter instanceof BasicInterface) {
                if ($adapter->plainAuth($username, $password) === true) {
                    $user = null;

                    try {
                        $user = new User($this->db->user->findOne(['username' => $adapter->getIdentifier()]));
                    } catch (User\Exception\NotFound $e) {
                        $this->logger->warning('failed connect authenticated user, user account does not exists', [
                            'category' => get_class($this),
                        ]);
                    }

                    $identity = $this->auth->createIdentity($adapter);
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
            return $this->db->user->findOne(['username' => $adapter->getIdentifier()]);
        } catch (User\Exception\NotFound $e) {
            $this->logger->warning('failed connect authenticated user, user account does not exists', [
                'category' => get_class($this),
            ]);

            return false;
        }
    }
}
