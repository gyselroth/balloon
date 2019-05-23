<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\CreationChallenge;

use Balloon\Server\User;
use Cose\Algorithms;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class CreationChallengeFactory
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
    public function __construct(Database $db, AuthenticationExtensionsClientInputs $auth_extensions, AuthenticatorSelectionCriteria $auth_criteria)
    {
        $this->db = $db;
        $this->auth_extensions = $auth_extensions;
        $this->auth_criteria = $auth_criteria;
    }

    /**
     * Token endpoint.
     */
    public function create(User $user, string $domain): array
    {
        // RP Entity
        $rpEntity = new PublicKeyCredentialRpEntity(
            'balloon',
            $domain,
            null
        );

        // User Entity
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getUsername(),
            (string) $user->getId(),
            $user->getUsername(),
            null
        );

        // Challenge
        $challenge = random_bytes(32);

        // Public Key Credential Parameters
        $publicKeyCredentialParametersList = [
            new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
        ];

        // Timeout
        $timeout = 20000;

        $key = new PublicKeyCredentialCreationOptions(
            $rpEntity,
            $userEntity,
            $challenge,
            $publicKeyCredentialParametersList,
            $timeout,
            [],
            $this->auth_criteria,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $this->auth_extensions
        );

        $data = json_decode(json_encode($key));

        $record = [
            'created' => new UTCDateTime(),
            'owner' => $user->getId(),
            'key' => $data,
        ];

        $result = $this->db->creation_challenges->insertOne($record);
        $record['id'] = $result->getInsertedId();
        $record['key'] = $key;

        return $record;
    }

    /**
     * Get challenge.
     */
    public function getOne(ObjectIdInterface $id): PublicKeyCredentialCreationOptions
    {
        $challenge = $this->db->creation_challenges->findOne([
            '_id' => $id,
        ]);

        if ($challenge === null) {
            throw new Exception\NotFound('creation challenge not found');
        }

        return PublicKeyCredentialCreationOptions::createfromArray($challenge['key']);
    }
}
