<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Migration\Delta;

use Balloon\App\Idp\Storage\UserCredentials;
use Balloon\Migration\Delta\DeltaInterface;
use MongoDB\Database;
use ParagonIE\Halite\KeyFactory;

class Installation implements DeltaInterface
{
    /**
     * MongoDB.
     *
     * @var Database
     */
    protected $db;

    /**
     * OAuth2 storage.
     *
     * @var UserCredentials
     */
    protected $storage;

    /**
     * Construct.
     */
    public function __construct(UserCredentials $storage, Database $db)
    {
        $this->storage = $storage;
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $this->storage->setClientDetails('balloon-client-web', null, null, 'password refresh_token password_mfa');
        $this->storage->setClientDetails('balloon-client-desktop', null, null, 'password refresh_token password_mfa');

        if ($this->db->oauth_keys->count() === 0) {
            $seal_keypair = KeyFactory::generateEncryptionKeyPair();
            $seal_secret = $seal_keypair->getSecretKey();
            $seal_public = $seal_keypair->getPublicKey();

            $this->db->oauth_keys->insertOne([
                'private_key' => KeyFactory::export($seal_secret)->getString(),
                'public_key' => KeyFactory::export($seal_public)->getString(),
            ]);
        }

        return true;
    }
}
