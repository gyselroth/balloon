<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\Device;

use Balloon\App\Webauthn\CredentialRepository;
use Micro\Http\Response;
use Symfony\Component\HttpFoundation\Request;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;

class DeviceFactory
{
    /**
     * CredentialRepository.
     *
     * @var CredentialRepository
     */
    protected $repository;

    /**
     * PublicKeyCredentialLoader.
     *
     * @var PublicKeyCredentialLoader
     */
    protected $loader;

    /**
     * AuthenticatorAttestationResponseValidator.
     *
     * @var AuthenticatorAttestationResponseValidator
     */
    protected $validaror;

    /**
     * Initialize.
     */
    public function __construct(CredentialRepository $repository, PublicKeyCredentialLoader $loader, AuthenticatorAttestationResponseValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
        $this->loader = $loader;
    }

    /**
     * Create device.
     */
    public function create(PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions, array $data)
    {
        // We init the PSR7 Request object
        $psr7Request = \Zend\Diactoros\ServerRequestFactory::fromGlobals();

        // Load the data
        $publicKeyCredential = $this->loader->load(json_encode($data));
        $response = $publicKeyCredential->getResponse();

        // Check if the response is an Authenticator Attestation Response
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Not an authenticator attestation response');
        }

        // Check the response against the request
        $this->validator->check($response, $publicKeyCredentialCreationOptions, $psr7Request);

        // You can get the Public Key Credential Source. This object should be persisted using the Public Key Credential Source repository
        $publicKeyCredentialSource = \Webauthn\PublicKeyCredentialSource::createFromPublicKeyCredential(
            $publicKeyCredential,
            $publicKeyCredentialCreationOptions->getUser()->getId()
        );

        $this->repository->saveCredentialSource($publicKeyCredentialSource);

        //You can also get the PublicKeyCredentialDescriptor.
        $publicKeyCredentialDescriptor = $publicKeyCredential->getPublicKeyCredentialDescriptor();

        // Normally this condition should be true. Just make sure you received the credential data
        $attestedCredentialData = null;
        if ($response->getAttestationObject()->getAuthData()->hasAttestedCredentialData()) {
            $attestedCredentialData = $response->getAttestationObject()->getAuthData()->getAttestedCredentialData();
        }
    }
}
