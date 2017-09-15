<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @category    Balloon
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2016 gyselroth GmbH
 */

namespace Balloon\App\Convert\Api\v1;

use \Balloon\App\Convert\Exception;
use \Micro\Http\Response;
use \Balloon\Api\Controller;
use \Balloon\App\Convert\Job;

class Convert extends Controller
{
    /**
     * @api {get} /api/v1/file/preview?id=:id Get Convert
     * @apiVersion 1
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
     * @param  string $id
     * @param  string $p
     * @param  string $encode
     * @return void
     */
    public function getSupportedFormats(?string $id=null, ?string $p=null): Response
    {
        $file = $this->fs->getNode($id, $p, 'File');
        $converter = $this->server->getApp()->getApp('Balloon.App.Convert')->getConverter();
        return (new Response())->setCode(200)->setBody($converter->getSupportedFormats($file));
    }
    

    public function getShadow(?string $id=null, ?string $p=null): Response
    {
        $file = $this->fs->getNode($id, $p, 'File');
        $app = $this->server->getApp()->getApp('Balloon.App.Convert');
        $shadow = $file->getAppAttribute($app, 'formats');

        return (new Response())->setCode(200)->setBody((array)$shadow);
    }

    public function postShadow(array $formats, ?string $id=null, ?string $p=null): Response
    {
        $file = $this->fs->getNode($id, $p, 'File');
        $app = $this->server->getApp()->getApp('Balloon.App.Convert');
        $converter = $app->getConverter();
        $supported = $converter->getSupportedFormats($file);

        $shadow = $file->getAppAttribute($app, 'formats');
        if ($shadow === null) {
            $shadow = [];
        }

        $queue = $file->getFilesystem()->getServer()->getAsync();
        foreach ($formats as $type) {
            if (!in_array($type, $supported)) {
                throw new Exception('format '.$type.' is not available for file');
            }

            $queue->addJob(new Job([
                'id' => $file->getId(),
                'format' => $type
            ]));
        }
        
        $file->setAppAttribute($app, 'formats', $formats);
        return (new Response())->setCode(204);
    }
    

    public function deleteShadow(string $format, ?string $id=null, ?string $p=null): Response
    {
    }
}
