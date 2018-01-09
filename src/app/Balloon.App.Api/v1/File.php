<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\Latest\File as LatestFile;
use Balloon\Helper;
use Micro\Http\Response;

class File extends LatestFile
{
    /**
     * @api {get} /api/v1/file/history?id=:id Get history
     * @apiVersion 1.0.0
     * @apiName getHistory
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Get a full change history of a file
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/file/history?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/file/544627ed3c58891f058b4686/history?pretty"
     * curl -XGET "https://SERVER/api/v1/file/history?p=/absolute/path/to/my/file&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} data History
     * @apiSuccess (200 OK) {number} data.version Version
     * @apiSuccess (200 OK) {object} data.changed Changed timestamp
     * @apiSuccess (200 OK) {number} data.changed.sec Changed timestamp in Unix time
     * @apiSuccess (200 OK) {number} data.changed.usec Additional microseconds to changed Unix timestamp
     * @apiSuccess (200 OK) {string} data.user User which changed the version
     * @apiSuccess (200 OK) {number} data.type Change type, there are five different change types including:</br>
     *  0 - Initially added</br>
     *  1 - Content modified</br>
     *  2 - Version rollback</br>
     *  3 - Deleted</br>
     *  4 - Undeleted
     * @apiSuccess (200 OK) {object} data.file Reference to the content
     * @apiSuccess (200 OK) {string} data.file.id Content reference ID
     * @apiSuccess (200 OK) {number} data.size Content size in bytes
     * @apiSuccess (200 OK) {string} data.mime Content mime type
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [
     *          {
     *              "version": 1,
     *              "changed": {
     *                  "sec": 1413883885,
     *                  "usec": 876000
     *              },
     *              "user": "peter.meier",
     *              "type": 0,
     *              "file": {
     *                  "$id": "544627ed3c58891f058b4688"
     *              },
     *              "size": 178,
     *              "mime": "text\/plain"
     *          }
     *      ]
     * }
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function getHistory(?string $id = null, ?string $p = null): Response
    {
        $result = Helper::escape(
            $this->_getNode($id, $p)->getHistory()
        );

        return (new Response())->setCode(200)->setBody($result);
    }
}
