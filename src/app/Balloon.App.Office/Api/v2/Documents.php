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
use Balloon\App\Office\Document as OfficeDoc;
use Balloon\App\Office\Template;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;

class Documents
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
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Constructor.
     */
    public function __construct(App $app, Server $server, AttributeDecorator $decorator)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->app = $app;
        $this->decorator = $decorator;
    }

    /**
     * Get document.
     */
    public function get(string $id): Response
    {
        $node = $this->fs->getNode($id, File::class);
        $document = new OfficeDoc($this->fs->getDatabase(), $node);
        $sessions = [];

        foreach ($document->getSessions() as $session) {
            $sessions[] = [
                'id' => (string) $session['_id'],
                'created' => $session['_id']->getTimestamp(),
                'user' => [
                    'id' => (string) $session['user'],
                    'name' => $this->server->getUserById($session['user'])->getUsername(),
                ],
            ];
        }

        $result = [
            'loleaflet' => $this->app->getLoleaflet(),
            'session' => $sessions,
        ];

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Create new empty document.
     */
    public function post(string $name, string $type, ?string $collection = null, int $conflict = 0, ?bool $readonly = null, ?array $meta = null): Response
    {
        $parent = $this->fs->getNode($collection, Collection::class, false, true);
        $tpl = new Template($type);

        $attributes = compact('readonly', 'meta');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $stream = $tpl->get();
        $storage = $parent->getStorage();
        $session = $storage->storeTemporaryFile($stream, $this->server->getIdentity());
        $result = $parent->addFile($name, $session, $attributes);
        fclose($stream);
        $result = $this->decorator->decorate($result);

        return (new Response())->setCode(201)->setBody($result);
    }
}
