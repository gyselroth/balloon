<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Api\v2;

use Balloon\App\Wopi\Template;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Server;
use Balloon\Session\Factory as SessionFactory;
use Micro\Http\Response;

class Documents
{
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
     * Session factory.
     *
     * @var SessionFactory
     */
    protected $session_factory;

    /**
     * Constructor.
     */
    public function __construct(Server $server, SessionFactory $session_factory, AttributeDecorator $decorator)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->session_factory = $session_factory;
        $this->decorator = $decorator;
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
        $session = $this->session_factory->add($this->server->getIdentity(), $parent, $stream);
        $result = $parent->addFile($name, $session, $attributes);
        fclose($stream);
        $result = $this->decorator->decorate($result);

        return (new Response())->setCode(201)->setBody($result);
    }
}
