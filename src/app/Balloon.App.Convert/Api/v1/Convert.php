<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\Api\v1;

use Balloon\Api\Controller;
use Balloon\App\Convert\App\Http As App;
use Balloon\App\Convert\Exception;
use Balloon\App\Convert\Job;
use Balloon\Async;
use Balloon\Converter;
use Balloon\Server;
use Micro\Http\Response;
use Balloon\Filesystem\Node\File;
use MongoDB\BSON\ObjectId;

class Convert extends Controller
{
    /**
     * Converter.
     *
     * @var Converter
     */
    protected $converter;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param App       $app
     * @param Converter $converter
     */
    public function __construct(Converter $converter, Server $server, Async $async)
    {
        $this->fs  = $server->getFilesystem();
        $this->converter = $converter;
        $this->async = $async;
    }

    /**
     * @api {get} /api/v1/convert/supported-formats?id=:id Get supported formats
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Get supported file formats to convert to (formats do vary between files)
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/file/convert/supported-formats?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [
     *          "png",
     *          "jpg",
     *          "tiff"
     *      ]
     * }
     *
     * @param string $id
     * @param string $p
     */
    public function getSupportedFormats(?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);

        return (new Response())->setCode(200)->setBody($this->converter->getSupportedFormats($file));
    }


    /**
     * @api {get} /api/v1/convert/slaves?id=:id Get slaves
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Get existing conversion slaves
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/file/convert/slaves?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [
     *      ]
     * }
     *
     * @param string $id
     * @param string $p
     */
    public function getSlaves(?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $slaves = $file->getAppAttribute('Balloon\\App\\Convert', 'slaves');

        return (new Response())->setCode(200)->setBody((array) $slaves);
    }


    /**
     * @api {post} /api/v1/convert/slave?id=:id Add new slave
     * @apiVersion 1.0.0
     * @apiName post
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Add new conversion slave
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v1/file/convert/slave?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "status": 200,
     *      "data": "944627ed3c58891f058b4686"
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $format
     */
    public function postSlave(string $format, ?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $supported = $this->converter->getSupportedFormats($file);

        $slaves = $file->getAppAttribute('Balloon\\App\\Convert', 'slaves');
        if (null === $slaves) {
            $slaves = [];
        }

        if (!in_array($format, $supported, true)) {
            throw new Exception('format '.$format.' is not available for file');
        }

        $id = new ObjectId();

        $this->async->addJob(Job::class, [
            'node' => $file->getId(),
            'slave' => $id,
        ]);

        $slaves[(string)$id] = [
            '_id' => $id,
            'format' => $format
        ];

        $file->setAppAttribute('Balloon\\App\\Convert', 'slaves', $slaves);

        return (new Response())->setCode(201)->setBody((string)$id);
    }


    /**
     * @api {delete} /api/v1/convert/slave?id=:id Delete slave
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Delete conversion slave
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XDELETE "https://SERVER/api/v1/file/convert/slave?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     * @param string $slave
     * @param bool $node
     */
    public function deleteSlave(string $slave, ?string $id = null, ?string $p = null, bool $node=false): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);

        $slaves = $file->getAppAttribute('Balloon\\App\\Convert', 'slaves');
        if(isset($slaves[$slave])) {
            if($node === true && isset($slaves[$slave]['node'])) {
                 $this->fs->getNodeById($slaves[$slave]['node'], File::class)->delete();
            }

            unset($slaves[$slave]);
            $file->setAppAttribute('Balloon\\App\\Convert', 'slaves', $slaves);

            return (new Response())->setCode(204);
        } else {
            throw new Exception('slave not found', Exception::SLAVE_NOT_FOUND);
        }
    }
}
