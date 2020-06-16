<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\v1\AttributeDecorator\RoleDecorator;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection as NodeCollection;
use Balloon\Helper;
use Balloon\Server\User;
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
     */
    public function headChildren(?string $id = null, ?string $p = null): Response
    {
        $result = $this->getNode($id, $p, null, false, true);
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
     */
    public function getChildren(
        ?string $id = null,
        ?string $p = null,
        int $deleted = 0,
        array $filter = [],
        array $attributes = []
    ): Response {
        $children = [];
        $nodes = $this->getNode($id, $p, NodeCollection::class, false, true)->getChildren($deleted, $filter);

        foreach ($nodes as $node) {
            $children[] = $this->node_decorator->decorate($node, $attributes);
        }

        return (new Response())->setCode(200)->setBody([
            'status' => 200,
            'data' => $children,
        ]);
    }

    /**
     * @api {get} /api/v1/collection/share?id=:id Get Share settings
     * @apiVersion 1.0.0
     * @apiName getShare
     * @apiGroup Node\Collection
     * @apiPermission none
     * @apiDescription Get share acl and share name
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452&pretty"
     * curl -XGET "https://SERVER/api/v1/collection/212323eeffe2322344224452/share?pretty"
     * curl -XGET "https://SERVER/api/v1/collection/share?p=/absolute/path/to/my/collection&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {string} data.name Share name
     * @apiSuccess (200 OK) {object[]} data.acl ACL rules
     * @apiSuccess (200 OK) {string} data.acl.type Either group or user
     * @apiSuccess (200 OK) {object} data.acl.role Role attributes
     * @apiSuccess (200 OK) {string} data.acl.priv Permission to access share
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data":{
     *          "name": "my share",
     *          "acl": [
     *              {
     *                  "type":"user",
     *                  "role": {
     *                      "id": "212323eeffe2322355224411",
     *                  },
     *                  "privilege":"rw"
     *              }
     *          ]
     *      }
     *}
     *
     * @param string $id
     * @param string $p
     */
    public function getShare(RoleDecorator $role_decorator, ?string $id = null, ?string $p = null, array $attributes = []): Response
    {
        $node = $this->getNode($id, $p);
        $rules = false;

        if ($node->isShare()) {
            $rules = [];
            $acl = $node->getAcl();

            foreach ($acl as &$rule) {
                $role = $role_decorator->decorate($rule['role'], $attributes);
                $rules[] = [
                    'type' => $rule['role'] instanceof User ? 'user' : 'group',
                    'priv' => $rule['privilege'],
                    'name' => $role['name'],
                    'id' => (string) $rule['id'],
                ];
            }
        }

        return (new Response())->setCode(200)->setBody([
            'status' => 200,
            'data' => $rules,
        ]);
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
     * @apiParam (POST Parameter) {object[]} acl ACL rules
     * @apiParam (POST Parameter) {string} acl.type user or group
     * @apiParam (POST Parameter) {string} acl.role Role id (user or group id)
     * @apiParam (POST Parameter) {string} acl.privilege Permission to access share, could be on of the following:</br>
     *  rw - READ/WRITE </br>
     *  r - READONLY </br>
     *  w+ - INBOX (Only Access to owned nodes) </br>
     *  m - Manage </br>
     *  d - DENY </br>
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
     */
    public function postShare(array $acl, ?string $id = null, ?string $p = null): Response
    {
        $rules = [];
        foreach ($acl as $type => $ruleset) {
            foreach ($ruleset as $rule) {
                $rules[] = [
                    'type' => $type,
                    'id' => $rule[$type],
                    'privilege' => $rule['priv'],
                ];
            }
        }

        $node = $this->getNode($id, $p);
        $result = $node->share($rules, $node->getName());

        if (null === $result) {
            return (new Response())->setCode(204);
        }

        return (new Response())->setCode(201)->setBody([
            'status' => 201,
            'data' => $result,
        ]);
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
     */
    public function deleteShare(?string $id = null, ?string $p = null): Response
    {
        $node = $this->getNode($id, $p);
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
     */
    public function post(
        ?string $id = null,
        ?string $p = null,
        ?string $name = null,
        array $attributes = [],
        int $conflict = 0
    ): Response {
        if (null !== $p && null !== $name) {
            throw new Exception\InvalidArgument('p and name can not be used at the same time');
        }

        $attributes = $this->_verifyAttributes($attributes);

        if (null === $id && null !== $p) {
            if (!is_string($p) || empty($p)) {
                throw new Exception\InvalidArgument('name must be a valid string');
            }

            $parent_path = dirname($p);
            $name = Helper::mb_basename($p);
            $parent = $this->findNodeByPath($parent_path, NodeCollection::class);
            $result = $parent->addDirectory($name, $attributes, $conflict)->getId();

            return (new Response())->setCode(201)->setBody([
                'status' => 201,
                'data' => (string) $result,
            ]);
        }

        if (null !== $id && null === $name) {
            throw new Exception\InvalidArgument('name must be set with id');
        }

        $parent = $this->getNode($id, null, null, false, true);
        $result = $parent->addDirectory((string) $name, $attributes, $conflict)->getId();

        return (new Response())->setCode(201)->setBody([
            'status' => 201,
            'data' => (string) $result,
        ]);
    }
}
