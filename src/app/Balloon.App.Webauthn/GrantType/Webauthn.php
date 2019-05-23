<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\GrantType;

use OAuth2\GrantType\GrantTypeInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use OAuth2\ResponseType\AccessTokenInterface;
use CBOR\Decoder;
use CBOR\OtherObject\OtherObjectManager;
use CBOR\Tag\TagObjectManager;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\Request;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Balloon\App\Webauthn\RequestChallenge\RequestChallengeFactory;
use Psr\Log\LoggerInterface;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Balloon\App\Webauthn\CredentialRepository;
use Balloon\Server;
use Micro\Auth\Auth;
use Micro\Auth\Adapter\None as AdapterNone;
use Balloon\Hook;

class Webauthn implements GrantTypeInterface
{
    /**
     * Challenge factory
     *
     * @var RequestChallengeFactory
     */
    protected $request_challenge_factory;

    /**
     * Server
     *
     * @var Server
     */
    protected $server;

    /**
     * Validator
     *
     * @var AuthenticatorAssertionResponseValidator
     */
    protected $validator;

    /**
     * Publickey load
     *
     * @var PublicKeyCredentialLoader
     */
    protected $loader;

    /**
     * Hook
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Auth
     *
     * @var Auth
     */
    protected $auth;

    /**
     * User details.
     *
     * @var array
     */
    protected $user = [];

    /**
     * Init.
     */
    public function __construct(RequestChallengeFactory $request_challenge_factory, Server $server, Auth $auth, PublicKeyCredentialLoader $loader, AuthenticatorAssertionResponseValidator $validator, Hook $hook, LoggerInterface $logger)
    {
        $this->request_challenge_factory = $request_challenge_factory;
        $this->server = $server;
        $this->validator = $validator;
        $this->loader = $loader;
        $this->auth = $auth;
        $this->hook = $hook;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryStringIdentifier()
    {
        return 'webauthn';
    }

    /**
     * {@inheritdoc}
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!$request->request('user') || !$request->request('public_key') || !$request->request('challenge')) {
            $response->setError(400, 'invalid_request', 'Missing parameters: "user", "public_key" and "challenge" required');

            return null;
        }

        $user = $request->request('user');
        $data = $request->request('public_key');

        // Retrieve the Options passed to the device
        $publicKeyCredentialRequestOptions = $this->request_challenge_factory->getOne(new ObjectId($request->request('challenge')));

        $psr7Request = \Zend\Diactoros\ServerRequestFactory::fromGlobals();

        // Load the data
        $publicKeyCredential = $this->loader->load(json_encode($data));
        $response = $publicKeyCredential->getResponse();

        // Check if the response is an Authenticator Assertion Response
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Not an authenticator assertion response');
        }

        try {
            $this->validator->check(
                $publicKeyCredential->getRawId(),
                $publicKeyCredential->getResponse(),
                $publicKeyCredentialRequestOptions,
                $psr7Request,
                $user,
            );
        } catch(\Exception $e) {
            $this->logger->error('failed to authenticate device', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            $response->setError(401, 'invalid_grant', 'device could not be authenticated: '.$e->getMessage());
        }

        $identity = $this->auth->createIdentity(new AdapterNone());
        $user = $this->server->getUserById(new ObjectId($request->request('user')));
        $this->hook->run('preServerIdentity', [$identity, &$user]);

        $user = $user->getAttributes();
        $user['user_id'] = $user['username'];
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
