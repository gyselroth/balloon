<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Auth;

use Balloon\Auth\InternalAuthInterface;
use Micro\Auth\Adapter\AbstractAdapter;
use Micro\Auth\Auth;
use MongoDB\Database;
use OAuth2\Request;
use OAuth2\Server as OAuth2Server;

class Token extends AbstractAdapter implements InternalAuthInterface
{
    /**
     * OAuth2 server.
     *
     * @var OAuth2Server
     */
    protected $server;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Internal.
     *
     * @var bool
     */
    protected $internal = true;

    /**
     * Auth.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Set options.
     */
    public function __construct(OAuth2Server $server, Database $db, Auth $auth)
    {
        $this->server = $server;
        $this->db = $db;
        $this->auth = $auth;
    }

    /**
     * Find identity.
     */
    public function findIdentity(string $username): ?array
    {
        return $this->db->user->findOne([
            '$or' => [
                ['username' => $username],
                ['mail' => $username],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function isInternal(): bool
    {
        return $this->internal;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(): bool
    {
        $request = Request::createFromGlobals();
        if ($this->server->verifyResourceRequest($request)) {
            $data = $this->server->getAccessTokenData($request);

            try {
                $adapter = $this->auth->getAdapter($data['adapter']);
                $this->internal = $adapter instanceof InternalAuthInterface;
            } catch (\Exception $e) {
                $this->internal = true;
            }

            $this->identifier = $data['user_id'];
            $this->attributes = $this->findIdentity($this->identifier);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
