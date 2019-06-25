<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\Migration\Delta;

use Balloon\App\Idp\Storage\Db as OAuth2Storage;
use Balloon\Migration\Delta\DeltaInterface;
use MongoDB\Database;

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
     * @var OAuth2Storage
     */
    protected $storage;

    /**
     * Construct.
     */
    public function __construct(OAuth2Storage $storage, Database $db)
    {
        $this->storage = $storage;
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $this->storage->setClientDetails('balloon-client-web', null, null, 'password refresh_token password_mfa webauthn webauthn_mfa');
        $this->storage->setClientDetails('balloon-client-desktop', null, null, 'password refresh_token password_mfa webauthn webauthn_mfa');
        $this->db->creation_challenges->createIndex(['created' => 1], ['expireAfterSeconds' => 60]);
        $this->db->request_challenges->createIndex(['created' => 1], ['expireAfterSeconds' => 60]);

        return true;
    }
}
