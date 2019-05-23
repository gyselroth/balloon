<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\RequestChallenge;

use Balloon\App\Webauthn\CredentialRepository;
use Balloon\Server\User;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;

class RequestChallengeFactory
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * AuthenticationExtensionsClientInputs.
     *
     * @var AuthenticationExtensionsClientInputs
     */
    protected $auth_extensions;

    /**
     * CredentialRepository.
     *
     * @var CredentialRepository
     */
    protected $repository;

    /**
     * Initialize.
     */
    public function __construct(Database $db, AuthenticationExtensionsClientInputs $auth_extensions, CredentialRepository $repository)
    {
        $this->db = $db;
        $this->auth_extensions = $auth_extensions;
        $this->repository = $repository;
    }

    /**
     * Token endpoint.
     */
    public function create(ObjectIdInterface $user, string $domain): array
    {
        $registeredPublicKeyCredentialDescriptors = iterator_to_array($this->repository->getDescriptorsByUser($user));

        // Public Key Credential Request Options
        $key = new PublicKeyCredentialRequestOptions(
            random_bytes(32),                                                           // Challenge
            60000,                                                                      // Timeout
            $domain,                                                          // Relying Party ID
            $registeredPublicKeyCredentialDescriptors,                                  // Registered PublicKeyCredentialDescriptor classes
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED, // User verification requirement
            $this->auth_extensions
        );

        $data = json_decode(json_encode($key));

        $record = [
            'created' => new UTCDateTime(),
            'owner' => $user,
            'key' => $data,
        ];

        $result = $this->db->request_challenges->insertOne($record);
        $record['id'] = $result->getInsertedId();
        $record['key'] = $key;

        return $record;
    }

    /**
     * Get challenge.
     */
    public function getOne(ObjectIdInterface $challenge): PublicKeyCredentialRequestOptions
    {
        $challenge = $this->db->request_challenges->findOne([
            '_id' => $challenge,
        ]);

        if ($challenge === null) {
            throw new Exception\NotFound('challenge not found');
        }

        return PublicKeyCredentialRequestOptions::createfromArray($challenge['key']);
    }
}
