<?php

declare(strict_types = 1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api\v1;

use Balloon\Exception;
use Balloon\Helper;
use Micro\Http\Response;

class Collection extends Node
{
    /**
     * @api {head} /api/v1/collection/children?id=:id children exists?
     * @apiVersion 1.0.0
     * @apiName head
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Check if collection has any children
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XHEAD "https://SERVER/api/v1/collection/children?id=544627ed3c58891f058b4686"
     * curl -XHEAD "https://SERVER/api/v1/collection/544627ed3c58891f058b4686/children"
     * curl -XHEAD "https://SERVER/api/v1/collection/children?p=/absolute/path/to/my/collection"
     *
     * @apiSuccessExample {json} Success-Response (Children exists):
     * HTTP/1.1 204 Not Content
     *
     * @apiErrorExample {json} Error-Response (No children exists):
     * HTTP/1.1 404 Not Found
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function headChildren(?string $id = null, ?string $p = null) : Response
    {
        $result = $this->fs->getNode($id, $p, null, false, true);
        $children = $result->getSize();

        $response = (new Response())
            ->setHeader('Content-Length', $children);

        if ($children > 0) {
            $response->setCode(204);
        } else {
            $response->setCode(404);
        }

        return $response;
    }

    /**
     * @api {get} /api/v1/collection/children Get children
     * @apiVersion 1.0.0
     * @apiName getChildren
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Find all children of a collection
     * @apiUse _getNode
     * @apiUse _nodeAttributes
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
    ) : Response {
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
    public function getShare(?string $id = null, ?string $p = null) : Response
    {
        $result = $this->fs->getNode($id, $p)->getAcl();

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
    public function postShare(array $acl, ?string $id = null, ?string $p = null) : Response
    {
        $node = $this->fs->getNode($id, $p);
        $result = $node->share($acl);

        if (null === $result) {
            return (new Response())->setCode(204);
        }

        return (new Response())->setCode(201)->setBody($result);
    }

    /**
     * @api {delete} /api/v1/collection/share?id=:id Delete share
     * @apiVersion 1.0.0
     * @apiName deleteShare
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Does only remove sharing options and transform a share back into a normal collection.
     * There won't be any data loss after this action. All existing references would be removed automatically.
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452"
     * curl -XDELETE "https://SERVER/api/v1/collection/212323eeffe2322344224452/share"
     * curl -XDELETE "https://SERVER/api/v1/collection/share?p=/absolute/path/to/my/collection"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function deleteShare(?string $id = null, ?string $p = null) : Response
    {
        $node = $this->fs->getNode($id, $p);
        $result = $node->unshare();

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v1/collection?id=:id Create collection
     * @apiVersion 1.0.0
     * @apiName post
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Create a new collection. You can create a new collection combining a parent collection (id) and a name (name)
     * or set an absolute path (p) to the new collection. Additionally it is possible to overwrite server generated timestamps like created or changed (attributes).
     * Via the more advanced option filter (attributes.filter) you can create a special collection which can contain any nodes based on the given filter.
     * For example a filter could be {mime: application/pdf}, therefore the collection would contain all files with mimetype application/pdf accessible by you.
     * (Attention this can result in a slow server response since you could create a filter where no indexes exists, therefore the database engine needs to search the entire database)
     * @apiUse _getNode
     * @apiUse _conflictNode
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/collection?id=544627ef3c58891f058b468f&name=MyNewFolder&pretty"
     * curl -XGET "https://SERVER/api/v1/collection/544627ef3c58891f058b468f?name=MyNewFolder&pretty"
     * curl -XGET "https://SERVER/api/v1/collection/?p=/absolute/path/to/my/collection&name=MyNewFolder&pretty&conflict=2"
     *
     * @apiParam (GET Parameter) {string} id Either id or p (path) of a node must be given.
     * @apiParam (GET Parameter) {string} p Either id or p (path) of a node must be given. If a path is given, no name must be set,
     * the path must contain the name of the new collection.
     * @apiParam (GET Parameter) {string} name A collection name must be set in conjuction with id, don't need to set with a path
     * @apiParam (GET Parameter) {object} attributes Overwrite some attributes which are usually generated on the server
     * @apiParam (GET Parameter) {number} attributes.created Set specific created timestamp (UNIX timestamp format)
     * @apiParam (GET Parameter) {number} attributes.changed Set specific changed timestamp (UNIX timestamp format)
     * @apiParam (GET Parameter) {number} attributes.destroy Set specific self-destroy timestamp (UNIX timestamp format)
     * @apiParam (GET Parameter) {array} attributes.filter Set specific set of children instead just parent=this
     *
     * @apiSuccess (201 Created) {number} status Status Code
     * @apiSuccess (201 Created) {string} data Node ID
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "status":201,
     *      "data": "544627ed3c58891f058b4682"
     * }
     *
     * @param string $id
     * @param string $p
     * @param array  $attributes
     * @param int    $conflict
     *
     * @return Response
     */
    public function post(
        ?string $id = null,
        ?string $p = null,
        ?string $name = null,
        array $attributes = [],
        int $conflict = 0
    ) : Response {
        if (null !== $p && null !== $name) {
            throw new Exception\InvalidArgument('p and name can not be used at the same time');
        }

        $attributes = $this->_verifyAttributes($attributes);

        if (null === $id && null !== $p) {
            if (!is_string($p) || empty($p)) {
                throw new Exception\InvalidArgument('name must be a valid string');
            }

            $parent_path = dirname($p);
            $name = basename($p);
            $parent = $this->fs->findNodeWithPath($parent_path, 'Collection');
            $result = $parent->addDirectory($name, $attributes, $conflict)->getId(true);

            return (new Response())->setCode(201)->setBody($result);
        }
        if (null !== $id && null === $name) {
            throw new Exception\InvalidArgument('name must be set with id');
        }
        $parent = $this->fs->getNode($id, null, null, false, true);

        if (!is_string($name) || empty($name)) {
            throw new Exception\InvalidArgument('name must be a valid string');
        }

        $result = $parent->addDirectory($name, $attributes, $conflict)->getId(true);

        return (new Response())->setCode(201)->setBody($result);
    }
}
