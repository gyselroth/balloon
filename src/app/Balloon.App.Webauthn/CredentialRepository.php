<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn;

use Base64Url\Base64Url;
use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Webauthn\AttestedCredentialData;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class CredentialRepository implements PublicKeyCredentialSourceRepository
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Initialize.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $result = $this->db->devices->findOne([
            'key.publicKeyCredentialId' => Base64Url::encode($publicKeyCredentialId),
        ]);

        if ($result === null) {
            return null;
        }

        $result['key']['credentialPublicKey'] = Base64Url::decode($result['key']['credentialPublicKey']);

        return PublicKeyCredentialSource::createFromArray($result['key']);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $key = json_decode(json_encode($publicKeyCredentialSource));
        $record = [
            'created' => new UTCDateTime(),
            'owner' => new ObjectId(base64_decode($key->userHandle)),
            'key' => $key,
        ];

        $this->db->devices->insertOne($record);
    }

    /**
     * Get credentials by user.
     */
    public function getDescriptorsByUser(ObjectIdInterface $user): Generator
    {
        $result = $this->db->devices->find([
            'owner' => $user,
        ]);

        foreach ($result as $resource) {
            yield PublicKeyCredentialSource::createFromArray($resource['key'])->getPublicKeyCredentialDescriptor();
        }
    }

    /**
     * Depreacted interface methods.
     */
    public function has(string $credentialId): bool
    {
    }

    public function get(string $credentialId): AttestedCredentialData
    {
    }

    public function getUserHandleFor(string $credentialId): string
    {
    }

    public function getCounterFor(string $credentialId): int
    {
    }

    public function updateCounterFor(string $credentialId, int $newCounter): void
    {
    }
}
