<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Api\Latest;

use Balloon\App\Api\Controller;
use Balloon\App\Preview\Preview as PreviewGetter;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;

class Preview extends Controller
{
    /**
     * Preview.
     *
     * @var PreviewGetter
     */
    protected $preview;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     *
     * @param PreviewGetter $preview
     * @param Server        $server
     */
    public function __construct(PreviewGetter $preview, Server $server)
    {
        $this->fs = $server->getFilesystem();
        $this->preview = $preview;
    }

    /**
     * @api {get} /api/v2/file/preview?id=:id Get Preview
     * @apiVersion 2.0.0
     * @apiName get
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Get a preview of the files content. The body either contains an encoded string or a jpeg binary
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/file/preview?id=544627ed3c58891f058b4686 > preview.jpg"
     * curl -XGET "https://SERVER/api/v2/file/544627ed3c58891f058b4686/preview > preview.jpg"
     * curl -XGET "https://SERVER/api/v2/file/preview?p=/absolute/path/to/my/file > preview.jpg"
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
        $node = $this->fs->getNode($id, $p, File::class);
        $data = $this->preview->getPreview($node);
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
