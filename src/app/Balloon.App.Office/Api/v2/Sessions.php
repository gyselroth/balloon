<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Api\v2;

use Balloon\App\Office\Constructor\Http as App;
use Balloon\App\Office\Document;
use Balloon\App\Office\Session as WopiSession;
use Balloon\App\Office\Session\Member;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Sessions
{
    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Constructor.
     */
    public function __construct(App $app, Server $server)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->app = $app;
    }

    /**
     * Create session.
     */
    public function post(string $id): Response
    {
        $node = $this->fs->getNode($id, File::class);
        $document = new Document($this->fs->getDatabase(), $node);
        $ttl = $this->app->getTokenTtl();

        $session = new WopiSession($this->fs, $document, $ttl);
        $member = new Member($this->fs->getUser(), $ttl);
        $session->join($member)
                ->store();

        return (new Response())->setCode(201)->setBody([
            'id' => (string) $session->getId(),
            'wopi_url' => $this->app->getWopiUrl(),
            'access_token' => $member->getAccessToken(),
            'access_token_ttl' => ($member->getTTL()->toDateTime()->format('U') * 1000),
        ]);
    }

    /**
     * Join session.
     */
    public function postJoin(ObjectId $id): Response
    {
        $session = WopiSession::getSessionById($this->fs, $id);
        $ttl = $this->app->getTokenTtl();
        $member = new Member($this->fs->getUser(), $ttl);
        $session->join($member)
                ->store();

        return (new Response())->setCode(200)->setBody([
            'id' => (string) $session->getId(),
            'wopi_url' => $this->app->getWopiUrl(),
            'access_token' => $member->getAccessToken(),
            'access_token_ttl' => ($member->getTTL()->toDateTime()->format('U') * 1000),
        ]);
    }

    /**
     * Delete session.
     */
    public function delete(ObjectId $id, string $access_token): Response
    {
        $session = WopiSession::getByAccessToken($this->server, $id, $access_token);
        $session->leave($this->fs->getUser())
                ->store();

        return (new Response())->setCode(204);
    }
}
