<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Sharelink\Sharelink as Share;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Server;
use Micro\Http\Response;

class ShareLink extends Controller
{
    /**
     * Sharelink.
     *
     * @var Share
     */
    protected $sharelink;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Node attribute decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Constructor.
     */
    public function __construct(Share $sharelink, Server $server, NodeAttributeDecorator $node_decorator)
    {
        $this->fs = $server->getFilesystem();
        $this->sharelink = $sharelink;
        $this->node_decorator = $node_decorator;
    }

    /**
     * Create share link.
     */
    public function post(?string $id = null, ?string $p = null, ?string $password = null, ?string $expiration = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $this->sharelink->shareLink($node, $expiration, $password);
        $result = $this->node_decorator->decorate($node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Delete share link.
     */
    public function delete(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $this->sharelink->deleteShareLink($node);

        return (new Response())->setCode(204);
    }
}
