<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi;

use Balloon\App\Wopi\Session\Session;
use Balloon\App\Wopi\Session\SessionInterface;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Balloon\Server\User;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class SessionManager
{
    /**
     * Valid until.
     *
     * @var int
     */
    protected $access_token_ttl = 3600;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * PostMessageOrigin
     * https://wopi.readthedocs.io/en/latest/scenarios/customization.html#postmessage-properties.
     *
     * @var string
     */
    protected $post_message_origin = 'http://localhost';

    /**
     * Session.
     */
    public function __construct(Database $db, Server $server, array $config = [])
    {
        $this->db = $db;
        $this->server = $server;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = []): SessionManager
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'access_token_ttl':
                    $this->access_token_ttl = (int) $value;

                    break;
                case 'post_message_origin':
                    $this->post_message_origin = (string) $value;
                    // no break
                default:
                    throw new InvalidArgumentException('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Authenticate.
     */
    public function authenticate(string $token): User
    {
        $result = $this->db->wopi->findOne([
            'token' => $token,
            'ttl' => [
                '$gte' => new UTCDateTime(),
            ],
        ]);

        if (null === $result) {
            throw new Exception\Forbidden('token is not valid for the requested node');
        }

        return $this->server->getUserById($result['user']);
    }

    /**
     * Get session by id.
     */
    public function getByToken(File $file, string $token): SessionInterface
    {
        $result = $this->db->wopi->findOne([
            'token' => $token,
            'ttl' => [
                '$gte' => new UTCDateTime(),
            ],
        ]);

        if (null === $result) {
            throw new Exception\Forbidden('session does not exists');
        }

        $user = $this->server->getUserById($result['user']);

        $result['post_message_origin'] = $this->post_message_origin;

        return new Session($file, $user, $result);
    }

    /**
     * Create session.
     */
    public function create(File $file, User $user): SessionInterface
    {
        $data = [
            'token' => $this->createToken(),
            'ttl' => new UTCDateTime((time() + $this->access_token_ttl) * 1000),
            'user' => $user->getId(),
            'node' => $file->getId(),
        ];

        $this->db->wopi->insertOne($data);

        $data['post_message_origin'] = $this->post_message_origin;

        return new Session($file, $user, $data);
    }

    /**
     * Create token.
     */
    protected function createToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
