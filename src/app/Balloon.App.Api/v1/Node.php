<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\Latest\Node as LatestNode;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\FileInterface;
use Balloon\Helper;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Micro\Http\Response;
use Psr\Log\LoggerInterface;

class Node extends LatestNode
{
    /**
     * Initialize.
     *
     * @param Server             $server
     * @param AttributeDecorator $decorator
     * @param LoggerInterface    $logger
     */
    public function __construct(Server $server, AttributeDecorator $decorator, RoleAttributeDecorator $role_decorator, LoggerInterface $logger)
    {
        parent::__construct($server, $decorator, $role_decorator, $logger);
        $this->registerv1Attributes();
    }

    /**
     * @apiDefine _nodeAttributes_v1
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object} data Attributes
     * @apiSuccess (200 OK) {string} data.id Unique node id
     * @apiSuccess (200 OK) {string} data.name Name
     * @apiSuccess (200 OK) {string} data.hash MD5 content checksum (file node only)
     * @apiSuccess (200 OK) {object} data.meta Extended meta attributes
     * @apiSuccess (200 OK) {string} data.meta.description UTF-8 Text Description
     * @apiSuccess (200 OK) {string} data.meta.color Color Tag (HEX) (Like: #000000)
     * @apiSuccess (200 OK) {string} data.meta.author Author
     * @apiSuccess (200 OK) {string} data.meta.mail Mail contact address
     * @apiSuccess (200 OK) {string} data.meta.license License
     * @apiSuccess (200 OK) {string} data.meta.copyright Copyright string
     * @apiSuccess (200 OK) {string[]} data.meta.tags Search Tags
     * @apiSuccess (200 OK) {number} data.size Size in bytes (Only file node), number of children if collection
     * @apiSuccess (200 OK) {string} data.mime Mime type
     * @apiSuccess (200 OK) {boolean} data.sharelink Is node shared?
     * @apiSuccess (200 OK) {number} data.version File version (file node only)
     * @apiSuccess (200 OK) {mixed} data.deleted Is boolean false if not deleted, if deleted it contains a deleted timestamp
     * @apiSuccess (200 OK) {number} data.deleted.sec Unix timestamp
     * @apiSuccess (200 OK) {number} data.deleted.usec Additional Microsecconds to Unix timestamp
     * @apiSuccess (200 OK) {object} data.changed Changed timestamp
     * @apiSuccess (200 OK) {number} data.changed.sec Unix timestamp
     * @apiSuccess (200 OK) {number} data.changed.usec Additional Microsecconds to Unix timestamp
     * @apiSuccess (200 OK) {object} data.created Created timestamp
     * @apiSuccess (200 OK) {number} data.created.sec Unix timestamp
     * @apiSuccess (200 OK) {number} data.created.usec Additional Microsecconds to Unix timestamp
     * @apiSuccess (200 OK) {boolean} data.share Node is shared
     * @apiSuccess (200 OK) {boolean} data.directory Is node a collection or a file?
     *
     * @apiSuccess (200 OK - additional attributes) {string} data.thumbnail Id of preview (file node only)
     * @apiSuccess (200 OK - additional attributes) {string} data.access Access if node is shared, one of r/rw/w
     * @apiSuccess (200 OK - additional attributes) {string} data.shareowner Username of the share owner
     * @apiSuccess (200 OK - additional attributes) {string} data.parent ID of the parent node
     * @apiSuccess (200 OK - additional attributes) {string} data.path Absolute node path
     * @apiSuccess (200 OK - additional attributes) {boolean} data.filtered Node is filtered (usually only a collection)
     * @apiSuccess (200 OK - additional attributes) {boolean} data.readonly Node is readonly
     * @apiSuccess (200 OK - additional attributes) {object[]} data.history Get file history (file node only)
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes, per default not all attributes would be returned
     */

    /**
     * @api {get} /api/v1/node/attributes?id=:id Get attributes
     * @apiVersion 1.0.0
     * @apiName getAttributes
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get attributes from one or multiple nodes
     * @apiUse _getNode
     * @apiUse _nodeAttributes_v1
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes, per default only a bunch of attributes would be returned, if you
     * need other attributes you have to request them (for example "path")
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/attributes?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/node/attributes?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted&pretty"
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686/attributes?pretty"
     * curl -XGET "https://SERVER/api/v1/node/attributes?p=/absolute/path/to/my/node&pretty"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *          "id": "544627ed3c58891f058b4686",
     *          "name": "api.php",
     *          "hash": "a77f23ed800fd7a600a8c2cfe8cc370b",
     *          "meta": {
     *              "license": "GPLv3"
     *          },
     *          "size": 178,
     *          "mime": "text\/plain",
     *          "sharelink": true,
     *          "version": 1,
     *          "deleted": false,
     *          "changed": {
     *              "sec": 1413883885,
     *              "usec": 869000
     *          },
     *          "created": {
     *              "sec": 1413883885,
     *              "usec": 869000
     *          },
     *          "share": false,
     *          "thumbnail": "544628243c5889b86d8b4568",
     *          "directory": false
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     * @param array  $attributes
     *
     * @return Response
     */

    /**
     * @api {get} /api/v1/node/parent?id=:id Get parent node
     * @apiVersion 1.0.0
     * @apiName getParent
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get system attributes of the parent node
     * @apiUse _getNode
     * @apiUse _nodeAttributes_v1
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/parent?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/node/parent?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted?pretty"
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686/parent?pretty"
     * curl -XGET "https://SERVER/api/v1/node/parent?p=/absolute/path/to/my/node&pretty"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *          "id": "544627ed3c58891f058b46cc",
     *          "name": "exampledir",
     *          "meta": {},
     *          "size": 3,
     *          "mime": "inode\/directory",
     *          "deleted": false,
     *          "changed": {
     *              "sec": 1413883885,
     *              "usec": 869000
     *          },
     *          "created": {
     *              "sec": 1413883885,
     *              "usec": 869000
     *          },
     *          "share": false,
     *          "directory": true
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     * @param array  $attributes
     *
     * @return Response
     */

    /**
     * @api {get} /api/v1/node/parents?id=:id Get parent nodes
     * @apiVersion 1.0.0
     * @apiName getParents
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get system attributes of all parent nodes. The hirarchy of all parent nodes is ordered in a
     * single level array beginning with the collection on the highest level.
     * @apiUse _getNode
     * @apiUse _nodeAttributes_v1
     * @apiSuccess (200 OK) {object[]} data Nodes
     *
     * @apiParam (GET Parameter) {boolean} [self=true] Include requested collection itself at the end of the list (Will be ignored if the requested node is a file)
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/parents?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/node/parents?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted&pretty"
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686/parents?pretty&self=1"
     * curl -XGET "https://SERVER/api/v1/node/parents?p=/absolute/path/to/my/node&self=1"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": [
     * {
     *              "id": "544627ed3c58891f058bbbaa",
     *              "name": "rootdir",
     *              "meta": {},
     *              "size": 1,
     *              "mime": "inode\/directory",
     *              "deleted": false,
     *              "changed": {
     *                  "sec": 1413883880,
     *                  "usec": 869001
     *              },
     *              },
     *              "created": {
     *                  "sec": 1413883880,
     *                  "usec": 869001
     *              },
     *              "share": false,
     *              "directory": true
     *          },
     *          {
     *              "id": "544627ed3c58891f058b46cc",
     *              "name": "parentdir",
     *              "meta": {},
     *              "size": 3,
     *              "mime": "inode\/directory",
     *              "deleted": false,
     *              "changed": {
     *                  "sec": 1413883885,
     *                  "usec": 869000
     *              },
     *              "created": {
     *                  "sec": 1413883885,
     *                  "usec": 869000
     *              },
     *              "share": false,
     *              "directory": true
     *          }
     *      ]
     * }
     *
     * @param string $id
     * @param string $p
     * @param array  $attributes
     *
     * @return Response
     */

    /**
     * @api {post} /api/v1/node/meta-attributes?id=:id Write meta attributes
     * @apiVersion 1.0.0
     * @apiName postMetaAttributes
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get meta attributes of a node
     * @apiUse _getNode
     *
     * @apiParam (POST Parameter) {string} [description] UTF-8 Text Description
     * @apiParam (POST Parameter) {string} [color] Color Tag (HEX) (Like: #000000)
     * @apiParam (POST Parameter) {string} [author] Author
     * @apiParam (POST Parameter) {string} [mail] Mail contact address
     * @apiParam (POST Parameter) {string} [license] License
     * @apiParam (POST Parameter) {string} [opyright] Copyright string
     * @apiParam (POST Parameter) {string[]} [tags] Search Tags
     *
     * @apiExample (cURL) example:
     * curl -XPOST -d author=peter.mier -d license="GPLv2" "https://SERVER/api/v1/node/meta-attributes?id=544627ed3c58891f058b4686"
     * curl -XPOST -d author=authorname "https://SERVER/api/v1/node/544627ed3c58891f058b4686/meta-attributes"
     * curl -XPOST -d license="GPLv3" "https://SERVER/api/v1/node/meta-attributes?p=/absolute/path/to/my/node"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function postMetaAttributes(?string $id = null, ?string $p = null): Response
    {
        $this->_getNode($id, $p)->setMetaAttribute(Helper::filter($_POST));

        return (new Response())->setCode(204);
    }

    /**
     * @api {get} /api/v1/node/query Custom query
     * @apiVersion 1.0.0
     * @apiName getQuery
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription A custom query is similar requet to children. You do not have to provide any parent node (id or p)
     * but you have to provide a filter therefore you can collect any nodes which do match the provided filter. It is a form of a search
     * (search) but does not use the search engine like GET /node/search does. You can also create a persistent query collection, just look at
     * POST /collection, there you can attach a filter option to the attributes paramater which would be the same as a custom query but just persistent.
     * Since query parameters can only be strings and you perhaps would like to filter other data types, you have to send json as parameter to the server.
     * @apiUse _nodeAttributes_v1
     *
     * @apiExample (cURL) example:
     * curl -XGET https://SERVER/api/v1/node/query?{%22filter%22:{%22shared%22:true,%22reference%22:{%22$exists%22:0}}}
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
     * @param int   $deleted
     * @param array $filter
     * @param array $attributes
     *
     * @return Response
     */

    /**
     * @api {get} /api/v1/node/trash Get trash
     * @apiName getTrash
     * @apiVersion 1.0.0
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription A similar endpoint to /api/v1/node/query filer={'deleted': {$type: 9}] but instead returning all deleted
     * nodes (including children which are deleted as well) this enpoint only returns the first deleted node from every subtree)
     * @apiUse _nodeAttributes_v1
     *
     * @apiExample (cURL) example:
     * curl -XGET https://SERVER/api/v1/node/trash?pretty
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter node attributes
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
     * @param array $attributes
     *
     * @return Response
     */

    /**
     * @api {get} /api/v1/node/search Search
     * @apiVersion 1.0.0
     * @apiName getSearch
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Extended search query, using the integrated search engine (elasticsearch).
     * @apiUse _nodeAttributes_v1
     *
     * @apiExample (cURL) example:
     * #Fulltext search and search for a name
     * curl -XGET -H 'Content-Type: application/json' "https://SERVER/api/v1/node/search?pretty" -d '{
     *           "body": {
     *               "query": {
     *                   "bool": {
     *                       "should": [
     *                           {
     *                               "match": {
     *                                   "content": "house"
     *                               }
     *                           },
     *                           {
     *                               "match": {
     *                                   "name": "file.txt"
     *                               }
     *                           }
     *                       ]
     *                   }
     *               }
     *           }
     *       }'
     *
     * @apiParam (GET Parameter) {object} query Elasticsearch query object
     * @apiParam (GET Parameter) {string[]} [attributes] Filter node attributes
     * @apiParam (GET Parameter) {number} [deleted=0] Wherever include deleted nodes or not, possible values:</br>
     * - 0 Exclude deleted</br>
     * - 1 Only deleted</br>
     * - 2 Include deleted</br>
     *
     * @apiSuccess (200 OK) {object[]} data Node list (matched nodes)
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data": [{...}, {...}]
     *      }
     * }
     *
     * @param array $query
     * @param array $attributes
     * @param int   $deleted
     *
     * @return Response
     */

    /**
     * @api {get} /api/v1/node/event-log?id=:id Event log
     * @apiVersion 1.0.0
     * @apiName getEventLog
     * @apiGroup Node
     * @apiPermission none
     * @apiUse _getNode
     * @apiDescription Get detailed event log
     * Request all modifications which are made by the user himself or share members.
     * Possible operations are the follwing:
     * - deleteCollectionReference
     * - deleteCollectionShare
     * - deleteCollection
     * - addCollection
     * - addFile
     * - addCollectionShare
     * - addCollectionReference
     * - undeleteFile
     * - undeleteCollectionReference
     * - undeleteCollectionShare
     * - restoreFile
     * - renameFile
     * - renameCollection
     * - renameCollectionShare
     * - renameCollectionRFeference
     * - copyFile
     * - copyCollection
     * - copyCollectionShare
     * - copyCollectionRFeference
     * - moveFile
     * - moveCollection
     * - moveCollectionReference
     * - moveCollectionShare
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/event-log?pretty"
     * curl -XGET "https://SERVER/api/v1/node/event-log?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686/event-log?pretty&limit=10"
     * curl -XGET "https://SERVER/api/v1/node/event-log?p=/absolute/path/to/my/node&pretty"
     *
     * @apiParam (GET Parameter) {number} [limit=100] Sets limit of events to be returned
     * @apiParam (GET Parameter) {number} [skip=0] How many events are skiped (useful for paging)
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object[]} data Events
     * @apiSuccess (200 OK) {number} data.event Event ID
     * @apiSuccess (200 OK) {object} data.timestamp event timestamp
     * @apiSuccess (200 OK) {number} data.timestamp.sec Event timestamp timestamp in Unix time
     * @apiSuccess (200 OK) {number} data.timestamp.usec Additional microseconds to changed Unix timestamp
     * @apiSuccess (200 OK) {string} data.operation event operation (like addCollection, deleteFile, ...)
     * @apiSuccess (200 OK) {string} data.parent ID of the parent node at the time of the event
     * @apiSuccess (200 OK) {object} data.previous Previous state of actual data which has been modified during an event, can contain either version, name or parent
     * @apiSuccess (200 OK) {number} data.previous.version Version at the time before the event
     * @apiSuccess (200 OK) {string} data.previous.name Name at the time before the event
     * @apiSuccess (200 OK) {string} data.previous.parent Parent node at the time before the event
     * @apiSuccess (200 OK) {string} data.share If of the shared folder at the time of the event
     * @apiSuccess (200 OK) {string} data.name Name of the node at the time of the event
     * @apiSuccess (200 OK) {object} data.node Current data of the node (Not from the time of the event!)
     * @apiSuccess (200 OK) {boolean} data.node.deleted True if the node is deleted, false otherwise
     * @apiSuccess (200 OK) {string} data.node.id Actual ID of the node
     * @apiSuccess (200 OK) {string} data.node.name Current name of the node
     * @apiSuccess (200 OK) {object} data.user Data which contains information about the user who executed an event
     * @apiSuccess (200 OK) {string} data.user.id Actual user ID
     * @apiSuccess (200 OK) {string} data.user.username Current username of executed event
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": [
     *          {
     *              "event": "57628e523c5889026f8b4570",
     *              "timestamp": {
     *                  "sec": 1466076753,
     *                  "usec": 988000
     *              },
     *              "operation": "restoreFile",
     *              "name": "file.txt",
     *              "previous": {
     *                  "version": 16
     *              },
     *              "node": {
     *                  "id": "558c0b273c588963078b457a",
     *                  "name": "3dddsceheckfile.txt",
     *                  "deleted": false
     *              },
     *              "parent": null,
     *              "user": {
     *                  "id": "54354cb63c58891f058b457f",
     *                  "username": "gradmin.bzu"
     *              },
     *              "share": null
     *          }
     *      ]
     * }
     *
     * @param string $id
     * @param string $p
     * @param int    $skip
     * @param int    $limit
     *
     * @return Response
     */
    public function getEventLog(?string $id = null, ?string $p = null, int $skip = 0, int $limit = 100): Response
    {
        if ($id !== null || $p !== null) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }

        $result = $this->fs->getDelta()->getEventLog($limit, $skip, $node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Downgrade latest api attributes to v1 attributes.
     */
    protected function registerv1Attributes(): void
    {
        $server = $this->server;
        $fs = $this->fs;

        $this->decorator->addDecorator('parent', function ($node, $requested) {
            $id = $node->getAttributes()['parent'];

            if (null === $id) {
                return null;
            }

            return (string) $id;
        });

        $this->decorator->addDecorator('shareowner', function ($node, $requested) use ($server, $fs) {
            if (!$node->isSpecial()) {
                return null;
            }

            try {
                return $server->getUserById($fs->findRawNode($node->getShareId())['owner'])->getUsername();
            } catch (\Exception $e) {
                return null;
            }
        });

        $this->decorator->addDecorator('history', function ($node, $requested) {
            if ($node instanceof FileInterface) {
                return $node->getHistory();
            }

            return null;
        });

        $this->decorator->addDecorator('filter', function ($node, $requested) {
            return null;
        });

        $this->decorator->addDecorator('owner', function ($node, $requested) {
            return null;
        });

        $this->decorator->addDecorator('shared', function ($node, $requested) {
            return null;
        });

        $this->decorator->addDecorator('sharename', function ($node, $requested) {
            return null;
        });

        $this->decorator->addDecorator('share', function ($node, $requested) {
            return $node->isShare();
        });

        $this->decorator->addDecorator('filtered', function ($node, $requested) {
            if (!($node instanceof Collection)) {
                return null;
            }

            return $node->getAttributes()['filter'];
        });
    }
}
