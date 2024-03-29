<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Api\v2;

use Balloon\App\Wopi\SessionManager;
use Balloon\Filesystem;
use Balloon\Server;
use Micro\Http\Response;

class Tokens
{
    /**
     * Session manager.
     *
     * @var SessionManager
     */
    protected $manager;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     */
    public function __construct(Server $server, SessionManager $manager)
    {
        $this->fs = $server->getFilesystem();
        $this->manager = $manager;
    }

    /**
     * Create session.
     */
    public function post(string $id): Response
    {
        $node = $this->fs->getNode($id);
        $session = $this->manager->create($node, $this->fs->getUser());

        return (new Response())->setCode(201)->setBody([
            'node' => (string) $node->getId(),
            'access_token' => $session->getAccessToken(),
            'ttl' => $session->getAccessTokenTTL(),
        ]);
    }
}
