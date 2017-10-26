<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Api\v1;

use Balloon\Api\Controller;
use Balloon\App\Preview\App;
use Balloon\Server;
use Micro\Http\Response;

class Preview extends Controller
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
     * @api {get} /api/v1/file/preview?id=:id Get Preview
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Get a preview of the files content. The body either contains an encoded string or a jpeg binary
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/file/preview?id=544627ed3c58891f058b4686 > preview.jpg"
     * curl -XGET "https://SERVER/api/v1/file/544627ed3c58891f058b4686/preview > preview.jpg"
     * curl -XGET "https://SERVER/api/v1/file/preview?p=/absolute/path/to/my/file > preview.jpg"
     *
     * @apiParam (GET Parameter) {string} [encode=false] Set to base64 to return a jpeg encoded preview as base64, else return it as jpeg binary
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @apiSuccessExample {binary} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @apiErrorExample {json} Error-Response (thumbnail not found):
     * HTTP/1.1 404 Not Found
     * {
     *      "status": 404,
     *      "data": {
     *          "error": "Balloon\\Exception\\NotFound",
     *          "message": "no preview exists"
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $encode
     */
    public function get(?string $id = null, ?string $p = null, ?string $encode = null): Response
    {
        $node = $this->fs->getNode($id, $p, 'File');
        $data = $this->app->getPreview($node);
        $response = (new Response())
            ->setHeader('Content-Type', 'image/png')
            ->setOutputFormat('text');

        if ('base64' === $encode) {
            $response->setBody(base64_encode($data), true);
        } else {
            $response->setBody($data, true);
        }

        return $response;
    }
}
