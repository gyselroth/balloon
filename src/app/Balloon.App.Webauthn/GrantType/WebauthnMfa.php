<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\GrantType;

use Balloon\App\Webauthn\RequestChallenge\RequestChallengeFactory;
use Balloon\Hook;
use Balloon\Server;
use Dolondro\GoogleAuthenticator\GoogleAuthenticator;
use Micro\Auth\Auth;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use Psr\Log\LoggerInterface;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;

class WebauthnMfa extends Webauthn
{
    /**
     * GoogleAuthenticator.
     *
     * @var GoogleAuthenticator
     */
    protected $authenticator;

    /**
     * User details.
     *
     * @var array
     */

    /**
     * Init.
     */
    public function __construct(RequestChallengeFactory $request_challenge_factory, Server $server, Auth $auth, PublicKeyCredentialLoader $loader, AuthenticatorAssertionResponseValidator $validator, Hook $hook, GoogleAuthenticator $authenticator, LoggerInterface $logger)
    {
        parent::__construct($request_challenge_factory, $server, $auth, $loader, $validator, $hook, $logger);
        $this->authenticator = $authenticator;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryStringIdentifier()
    {
        return 'webauthn_mfa';
    }

    /**
     * {@inheritdoc}
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!$request->request('user') || !$request->request('public_key') || !$request->request('challenge') || !$request->request('code')) {
            $response->setError(400, 'invalid_request', 'Missing parameters: "user", "code", "public_key" and "challenge" required');

            return null;
        }

        $result = parent::validateRequest($request, $response);

        if (!isset($this->user['google_auth_secret'])) {
            throw new \LogicException('no google authenticator secret available');
        }

        if ($this->authenticator->authenticate($this->user['google_auth_secret'], $request->request('code')) === false) {
            $response->setError(400, 'invalid_grant', 'Invalid auth code provided');

            return null;
        }

        return $result;
    }
}
