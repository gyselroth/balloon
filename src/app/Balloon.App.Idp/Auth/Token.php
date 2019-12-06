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
use Psr\Http\Message\ServerRequestInterface;
use Micro\Auth\IdentityInterface;
use Balloon\User\Factory as UserFactory;

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
        $this->identity_attribute = 'username';
    }

    /**
     * Find identity.
     */
    public function findIdentity(string $username): ?array
    {
        return $this->db->{UserFactory::COLLECTION_NAME}->findOne([
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
     * Transform PSR request into incompatible oauth-server request
     */
    protected function toOAuthRequest(ServerRequestInterface $request): Request
    {
        return new Request(
            $request->getQueryParams(),
            [],
            $request->getAttributes(),
            $request->getCookieParams(),
            $request->getUploadedFiles(),
            $request->getServerParams(),
            $request->getBody(),
            $request->getHeaders()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(ServerRequestInterface $request): ?array
    {
        $request = $this->toOAuthRequest($request);

        if ($this->server->verifyResourceRequest($request)) {
            $data = $this->server->getAccessTokenData($request);

            try {
                $adapter = $this->auth->getAdapter($data['adapter']);
                $this->internal = $adapter instanceof InternalAuthInterface;
            } catch (\Throwable $e) {
                $this->internal = true;
            }

            return $this->findIdentity($data['user_id']);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(IdentityInterface $identity): array
    {
        return [];
    }
}
