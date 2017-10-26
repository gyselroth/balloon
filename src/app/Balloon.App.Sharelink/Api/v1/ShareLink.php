<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink\Api\v1;

use Balloon\Api\Controller;
use Balloon\App\Sharelink\Http as App;
use Balloon\Helper;
use Micro\Http\Response;
use Balloon\Server;

class ShareLink extends Controller
{
    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Constructor.
     *
     * @param App    $app
     * @param Server $server
     */
    public function __construct(App $app, Server $server)
    {
        parent::__construct($server);
        $this->app = $app;
    }

    /**
     * @api {post} /api/v1/node/share-link?id=:id Create sharing link
     * @apiVersion 1.0.0
     * @apiName postShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Create a unique sharing link of a node (global accessible):
     * a possible existing link will be deleted if this method will be called.
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiParam (POST Parameter) {object} [options] Sharing options
     * @apiParam (POST Parameter) {number} [options.expiration] Expiration unix timestamp of the sharing link
     * @apiParam (POST Parameter) {string} [options.password] Protected shared link with password
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686&pretty"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/share-link?pretty"
     * curl -XPOST "https://SERVER/api/v1/node/share-link?p=/absolute/path/to/my/node&pretty"
     *
     * @apiSuccessExample {json} Success-Response (Created or modified share link):
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     * @param array  $options
     *
     * @return Response
     */
    public function post(?string $id = null, ?string $p = null, array $options = []): Response
    {
        $node = $this->fs->getNode($id, $p);
        $options = Helper::filter($options);
        $options['shared'] = true;

        $this->app->shareLink($node, $options);

        return (new Response())->setCode(204);
    }

    /**
     * @api {delete} /api/v1/node/share-link?id=:id Delete sharing link
     * @apiVersion 1.0.0
     * @apiName deleteShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Delete an existing sharing link
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686?pretty"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function delete(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $options = ['shared' => false];

        $this->app->shareLink($node, $options);

        return (new Response())->setCode(204);
    }

    /**
     * @api {get} /api/v1/node/share-link?id=:id Get sharing link
     * @apiVersion 1.0.0
     * @apiName getShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get an existing sharing link
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686/share-link?pretty"
     * curl -XGET "https://SERVER/api/v1/node/share-link?p=/path/to/my/node&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object} data Share options
     * @apiSuccess (200 OK) {string} data.token Shared unique node token
     * @apiSuccess (200 OK) {string} [data.password] Share link is password protected
     * @apiSuccess (200 OK) {string} [data.expiration] Unix timestamp
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *        "token": "544627ed3c51111f058b468654db6b7daca8e5.69846614",
     *     }
     * }
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function get(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $result = Helper::escape(
            $this->app->getShareLink($node)
        );

        return (new Response())->setCode(200)->setBody($result);
    }
}
