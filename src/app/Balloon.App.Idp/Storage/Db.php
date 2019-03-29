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
use MongoDB\BSON\UTCDateTime;
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
     * Last adapter.
     *
     * @var string
     */
    protected $adapter;

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
        foreach ($this->auth->getAdapters() as $name => $adapter) {
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

                        $this->adapter = $name;

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

    /**
     * {@inheritdoc}
     */
    public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null)
    {
        $token = [
            'access_token' => $access_token,
            'client_id' => $client_id,
            'expires' => new UTCDateTime($expires * 1000),
            'user_id' => $user_id,
            'scope' => $scope,
            'adapter' => $this->adapter,
        ];

        if ($this->getAccessToken($access_token)) {
            $result = $this->collection('access_token_table')->updateOne(
                ['access_token' => $access_token],
                ['$set' => $token]
            );

            return $result->getMatchedCount() > 0;
        }

        $result = $this->collection('access_token_table')->insertOne($token);

        return $result->getInsertedCount() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        $expires = new UTCDateTime($expires * 1000);

        return parent::setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope, $id_token);
    }

    /**
     * {@inheritdoc}
     */
    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        $expires = new UTCDateTime($expires * 1000);

        return parent::setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken($access_token)
    {
        $token = $this->collection('access_token_table')->findOne(['access_token' => $access_token]);

        if ($token === null) {
            return false;
        }

        $token['expires'] = $token['expires']->toDateTime()->format('U');

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationCode($code)
    {
        $code = $this->collection('code_table')->findOne([
            'authorization_code' => $code,
        ]);

        if ($code === null) {
            return false;
        }

        $code['expires'] = $code['expires']->toDateTime()->format('U');

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefreshToken($refresh_token)
    {
        $token = $this->collection('refresh_token_table')->findOne(['refresh_token' => $refresh_token]);

        if ($token === null) {
            return false;
        }

        $token['expires'] = $token['expires']->toDateTime()->format('U');

        return $token;
    }
}
