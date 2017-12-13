<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\Helper;
use Micro\Http\Response;

class Collection extends Node
{
    /**
     * @api {get} /api/v1/collection/children Get children
     * @apiVersion 1.0.0
     * @apiName getChildren
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Find all children of a collection
     * @apiUse _getNode
     * @apiUse _nodeAttributes_v1
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/collection/children?id=212323eeffe2322344224452&pretty"
     * curl -XGET "https://SERVER/api/v1/collection/212323eeffe2322344224452/children?pretty&deleted=0"
     * curl -XGET "https://SERVER/api/v1/collection/children?p=/absolute/path/to/my/collection&deleted=1"
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter node attributes
     * @apiParam (GET Parameter) {string[]} [filter] Filter nodes
     * @apiParam (GET Parameter) {number} [deleted=0] Wherever include deleted nodes or not, possible values:</br>
     * - 0 Exclude deleted</br>
     * - 1 Only deleted</br>
     * - 2 Include deleted</br>
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} data Children
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data": [{..}, {...}] //Shorted
     * }
     *
     * @param string $id
     * @param string $p
     * @param int    $deleted
     * @param array  $filter
     * @param array  $attributes
     *
     * @return Response
     */
    public function getChildren(
        ?string $id = null,
        ?string $p = null,
        int $deleted = 0,
        array $filter = [],
        array $attributes = []
    ): Response {
        $children = [];
        $nodes = $this->fs->getNode($id, $p, null, false, true)->getChildNodes($deleted, $filter);

        foreach ($nodes as $node) {
            $children[] = Helper::escape($this->decorator->decorate($node, $attributes));
        }

        return (new Response())->setCode(200)->setBody($children);
    }

    /**
     * @api {get} /api/v1/collection/share?id=:id Get Share parameters
     * @apiVersion 1.0.0
     * @apiName getShare
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Get share parameters
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452&pretty"
     * curl -XGET "https://SERVER/api/v1/collection/212323eeffe2322344224452/share?pretty"
     * curl -XGET "https://SERVER/api/v1/collection/share?p=/absolute/path/to/my/collection&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} data Share ACL with roles and permissions
     * @apiSuccess (200 OK) {string} data.type Either group or user
     * @apiSuccess (200 OK) {string} data.id A unique role identifier
     * @apiSuccess (200 OK) {string} data.name Could be the same as id, but don't have to (human readable name)
     * @apiSuccess (200 OK) {string} data.priv Permission to access share
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data":[
     *          {
     *              "type":"user",
     *              "id":"peter.meier",
     *              "name":"peter.meier",
     *              "priv":"rw"
     *          }
     *      ]
     *}
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function getShare(?string $id = null, ?string $p = null): Response
    {
        $result = $this->fs->getNode($id, $p)->getShare();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {post} /api/v1/collection/share?id=:id Create share
     * @apiVersion 1.0.0
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Create a new share from an existing collection
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452&pretty"
     *
     * @apiParam (POST Parameter) {object} acl Share ACL
     * @apiParam (POST Parameter) {object[]} acl.user User ACL rules
     * @apiParam (POST Parameter) {string} acl.user.user Username which should match ACL rule
     * @apiParam (POST Parameter) {string} acl.user.priv Permission to access share, could be on of the following:</br>
     *  rw - READ/WRITE </br>
     *  r - READONLY </br>
     *  w - WRITEONLY </br>
     *  d - DENY </br>
     * @apiParam (POST Parameter) {object[]} acl.group Group ACL rules
     * @apiParam (POST Parameter) {string} acl.group Groupname which should match ACL rule
     * @apiParam (POST Parameter) {string} acl.priv Permission to access share, see possible permissions above
     *
     * @apiSuccess (201 Created) {number} status Status code
     * @apiSuccess (201 Created) {boolean} data
     * @apiSuccessExample {json} Success-Response (Created or Modified Share):
     * HTTP/1.1 201 Created
     * {
     *      "status":201,
     *      "data": true
     * }
     *
     * @apiSuccessExample {json} Success-Response (Removed share):
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     * @param array  $acl
     *
     * @return Response
     */
    public function postShare(array $acl, ?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $result = $node->share($acl);

        if ($result === null) {
            return (new Response())->setCode(204);
        }

        return (new Response())->setCode(201)->setBody($result);
    }
}
