<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\GrantType;

use Balloon\Server;
use Dolondro\GoogleAuthenticator\GoogleAuthenticator;
use OAuth2\GrantType\GrantTypeInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\Storage\UserCredentialsInterface;

class UserCredentialsMultiFactor implements GrantTypeInterface
{
    /**
     * Storage.
     *
     * @var UserCredentialsInterface
     */
    protected $storage;

    /**
     * Google authenticator.
     *
     * @var GoogleAuthenticator
     */
    protected $authenticator;

    /**
     * User details.
     *
     * @var array
     */
    private $user = [];

    /**
     * Server.
     *
     * @var Server
     */
    private $server;

    /**
     * Init.
     */
    public function __construct(UserCredentialsInterface $storage, GoogleAuthenticator $authenticator, Server $server)
    {
        $this->storage = $storage;
        $this->authenticator = $authenticator;
        $this->server = $server;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryStringIdentifier()
    {
        return 'password_mfa';
    }

    /**
     * {@inheritdoc}
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!$request->request('password') || !$request->request('username') || !$request->request('code')) {
            $response->setError(400, 'invalid_request', 'Missing parameters: "username", "password" and "code" required');

            return null;
        }

        if (!$this->storage->checkUserCredentials($request->request('username'), $request->request('password'))) {
            $response->setError(401, 'invalid_grant', 'Invalid username and password combination');

            return null;
        }

        $user = $this->storage->getUserDetails($request->request('username'));
        if (empty($user)) {
            $response->setError(400, 'invalid_grant', 'Unable to retrieve user information');

            return null;
        }

        if (!isset($user['user_id'])) {
            throw new \LogicException('you must set the user_id on the array returned by getUserDetails');
        }

        if (!isset($user['google_auth_secret'])) {
            throw new \LogicException('no google authenticator secret available');
        }

        if ($this->authenticator->authenticate($user['google_auth_secret'], $request->request('code')) === false) {
            $code = hash('sha512', $request->request('code'));
            if (($key = array_search($code, $user['multi_factor_recovery'])) !== false) {
                $codes = $user['multi_factor_recovery'];
                array_splice($codes, $key, $key);

                $this->server->getUserById($user['_id'])->setAttributes([
                'multi_factor_recovery' => $codes,
              ]);

                $this->user = $user;

                return true;
            }

            $response->setError(400, 'invalid_grant', 'Invalid auth code provided');

            return null;
        }

        $this->user = $user;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientId()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserId()
    {
        return $this->user['user_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function getScope()
    {
        return isset($this->user['scope']) ? $this->user['scope'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        return $accessToken->createAccessToken($client_id, $user_id, $scope);
    }
}
