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
     * @api {post} /api/v2/nodes/:id/share-link Create share link
     * @apiVersion 2.0.0
     * @apiName postShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Create a unique sharing link of a node (global accessible):
     * a possible existing link will be deleted if this method will be called.
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiParam (POST Parameter) {number} [expiration] Expiration unix timestamp of the sharing link
     * @apiParam (POST Parameter) {string} [password] Protected shared link with password
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v2/node/share-link?id=544627ed3c58891f058b4686&pretty"
     * curl -XPOST "https://SERVER/api/v2/node/544627ed3c58891f058b4686/share-link?pretty"
     * curl -XPOST "https://SERVER/api/v2/node/share-link?p=/absolute/path/to/my/node&pretty"
     *
     * @apiSuccessExample {json} Success-Response (Created or modified share link):
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b4686"
     * }
     *
     * @param string $id
     * @param string $p
     */
    public function post(?string $id = null, ?string $p = null, ?string $password = null, ?string $expiration = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $this->sharelink->shareLink($node, $expiration, $password);
        $result = $this->node_decorator->decorate($node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {delete} /api/v2/nodes/:id/share-link Delete share link
     * @apiVersion 2.0.0
     * @apiName deleteShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Delete an existing sharing link
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v2/node/share-link?id=544627ed3c58891f058b4686?pretty"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     */
    public function delete(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $this->sharelink->deleteShareLink($node);

        return (new Response())->setCode(204);
    }
}
