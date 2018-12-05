<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Api\Helper as ApiHelper;
use Balloon\App\Api\v2\Collections as ApiCollection;
use Balloon\App\Api\v2\Files as ApiFile;
use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem;
use Balloon\Filesystem\DeltaAttributeDecorator;
use Balloon\Filesystem\EventAttributeDecorator;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Helper;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Http\Response;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use ZipStream\ZipStream;

class Nodes extends Controller
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Node attribute decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Initialize.
     */
    public function __construct(Server $server, NodeAttributeDecorator $decorator, LoggerInterface $logger)
    {
        $this->fs = $server->getFilesystem();
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->node_decorator = $decorator;
        $this->logger = $logger;
    }

    /**
     * @api {head} /api/v2/nodes/:id Node exists?
     * @apiVersion 2.0.0
     * @apiName head
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Check if a node exists. Per default deleted nodes are ignore which means it will
     *  return a 404 if a deleted node is requested. You can change this behaviour via the deleted parameter.
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XHEAD "https://SERVER/api/v2/node?id=544627ed3c58891f058b4686"
     * curl -XHEAD "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686"
     * curl -XHEAD "https://SERVER/api/v2/node?p=/absolute/path/to/my/node"
     *
     * @apiParam (GET Parameter) {number} [deleted=0] Wherever include deleted node or not, possible values:</br>
     * - 0 Exclude deleted</br>
     * - 1 Only deleted</br>
     * - 2 Include deleted</br>
     *
     * @apiSuccessExample {json} Success-Response (Node does exist):
     * HTTP/1.1 200 OK
     *
     * @apiSuccessExample {json} Success-Response (Node does not exist):
     * HTTP/1.1 404 Not Found
     *
     * @param string $id
     * @param string $p
     */
    public function head(?string $id = null, ?string $p = null, int $deleted = 0): Response
    {
        try {
            $result = $this->_getNode($id, $p, null, false, false, $deleted);

            $response = (new Response())
                ->setHeader('Content-Length', (string) $result->getSize())
                ->setHeader('Content-Type', $result->getContentType())
                ->setCode(200);

            return $response;
        } catch (\Exception $e) {
            return (new Response())->setCode(404);
        }
    }

    /**
     * @api {post} /api/v2/nodes/:id/undelete Restore node
     * @apiVersion 2.0.0
     * @apiName postUndelete
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Undelete (Restore from trash) a single node or multiple ones.
     * @apiUse _getNodes
     * @apiUse _conflictNode
     * @apiUse _multiError
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v2/nodes/undelete?id[]=544627ed3c58891f058b4686&id[]=544627ed3c58891f058b46865&pretty"
     * curl -XPOST "https://SERVER/api/v2/nodes/undelete?id=544627ed3c58891f058b4686?pretty"
     * curl -XPOST "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686/undelete?conflict=2"
     * curl -XPOST "https://SERVER/api/v2/nodes/undelete?p=/absolute/path/to/my/node&conflict=0&move=1&destid=544627ed3c58891f058b46889"
     *
     * @apiParam (GET Parameter) {string} [destid] Either destid or destp (path) of the new parent collection node must be given.
     * @apiParam (GET Parameter) {string} [destp] Either destid or destp (path) of the new parent collection node must be given.
     *
     * @apiSuccessExample {json} Success-Response (conflict=1):
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b4686",
     *      "name": "renamed (xy23)"
     * }
     *
     * @param array|string $id
     * @param array|string $p
     * @param string       $destid
     * @param string       $destp
     */
    public function postUndelete(
        $id = null,
        $p = null,
        bool $move = false,
        ?string $destid = null,
        ?string $destp = null,
        int $conflict = 0
    ): Response {
        $parent = null;
        if (true === $move) {
            try {
                $parent = $this->_getNode($destid, $destp, 'Collection', false, true);
            } catch (Exception\NotFound $e) {
                throw new Exception\NotFound(
                    'destination collection was not found or is not a collection',
                    Exception\NotFound::DESTINATION_NOT_FOUND
                );
            }
        }

        return $this->bulk($id, $p, function ($node) use ($parent, $conflict, $move) {
            if (true === $move) {
                $node = $node->setParent($parent, $conflict);
            }

            if ($node->isDeleted()) {
                $node->undelete($conflict);
            }

            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node),
            ];
        });
    }

    /**
     * @api {get} /api/v2/nodes/:id/content Download stream
     * @apiVersion 2.0.0
     * @apiName getContent
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Download node contents. Collections are zipped during streaming.
     * @apiUse _getNode
     * @apiParam (GET Parameter) {boolean} [download=false] Force download file (Content-Disposition: attachment HTTP header)
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/node?id=544627ed3c58891f058b4686" > myfile.txt
     * curl -XGET "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686" > myfile.txt
     * curl -XGET "https://SERVER/api/v2/node?p=/absolute/path/to/my/collection" > folder.zip
     *
     * @apiSuccessExample {binary} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @apiErrorExample {json} Error-Response (Invalid offset):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "invalid offset requested",
     *          "code": 277
     *      }
     * }
     *
     * @param array|string $id
     * @param array|string $p
     */
    public function getContent(
        $id = null,
        $p = null,
        bool $download = false,
        string $name = 'selected'
    ): ?Response {
        if (is_array($id) || is_array($p)) {
            return $this->combine($id, $p, $name);
        }

        $node = $this->_getNode($id, $p);
        if ($node instanceof Collection) {
            return $node->getZip();
        }

        $response = new Response();

        return ApiHelper::streamContent($response, $node, $download);
    }

    /**
     * @apiDefine _nodeAttributes
     *
     * @apiSuccess (200 OK) {string} id Unique node id
     * @apiSuccess (200 OK) {string} name Name
     * @apiSuccess (200 OK) {string} hash MD5 content checksum (file only)
     * @apiSuccess (200 OK) {object} meta Extended meta attributes
     * @apiSuccess (200 OK) {string} meta.description UTF-8 Text Description
     * @apiSuccess (200 OK) {string} meta.color Color Tag (HEX) (Like: #000000)
     * @apiSuccess (200 OK) {string} meta.author Author
     * @apiSuccess (200 OK) {string} meta.mail Mail contact address
     * @apiSuccess (200 OK) {string} meta.license License
     * @apiSuccess (200 OK) {string} meta.copyright Copyright string
     * @apiSuccess (200 OK) {string[]} meta.tags Search Tags
     * @apiSuccess (200 OK) {number} size Size in bytes (file only), number of children if collection
     * @apiSuccess (200 OK) {string} mime Mime type
     * @apiSuccess (200 OK) {boolean} sharelink Is node shared?
     * @apiSuccess (200 OK) {number} version File version (file only)
     * @apiSuccess (200 OK) {mixed} deleted Is boolean false if not deleted, if deleted it contains a deleted timestamp
     * @apiSuccess (200 OK) {string} deleted ISO8601 timestamp, only set if node is deleted
     * @apiSuccess (200 OK) {string} changed ISO8601 timestamp
     * @apiSuccess (200 OK) {string} created ISO8601 timestamp
     * @apiSuccess (200 OK) {string} destroy ISO8601 timestamp, only set if node has a destroy timestamp set
     * @apiSuccess (200 OK) {boolean} share Node is shared
     * @apiSuccess (200 OK) {boolean} directory Is true if the node is a collection
     * @apiSuccess (200 OK) {string} access Access permission for the authenticated user (d/r/rw/m)
     * @apiSuccess (200 OK) {object} shareowner Share owner
     * @apiSuccess (200 OK) {object} parent Parent node
     * @apiSuccess (200 OK) {string} path Absolute node path
     * @apiSuccess (200 OK) {string} filter Node is filtered (collection only)
     * @apiSuccess (200 OK) {boolean} readonly Readonly
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes
     *
     * @param null|mixed $id
     * @param null|mixed $p
     */

    /**
     * @api {get} /api/v2/nodes/:id Get attributes
     * @apiVersion 2.0.0
     * @apiName get
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get attributes from one or multiple nodes
     * @apiUse _getNode
     * @apiUse _nodeAttributes
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes, per default only a bunch of attributes would be returned, if you
     * need other attributes you have to request them (for example "path")
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/node?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v2/node?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted&pretty"
     * curl -XGET "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686?pretty"
     * curl -XGET "https://SERVER/api/v2/node?p=/absolute/path/to/my/node&pretty"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b4686",
     *      "name": "api.php",
     *      "hash": "a77f23ed800fd7a600a8c2cfe8cc370b",
     *      "meta": {
     *          "license": "GPLv3"
     *      },
     *      "size": 178,
     *      "mime": "text\/plain",
     *      "sharelink": true,
     *      "version": 1,
     *      "changed": "2007-08-31T16:47+00:00",
     *      "created": "2007-08-31T16:47+00:00",
     *      "share": false,
     *      "directory": false
     * }
     *
     * @param array|string $id
     * @param array|string $p
     */
    public function get($id = null, $p = null, int $deleted = 0, array $query = [], array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if ($id === null && $p === null) {
            if ($this instanceof ApiFile) {
                $query['directory'] = false;
                $uri = '/api/v2/files';
            } elseif ($this instanceof ApiCollection) {
                $query['directory'] = true;
                $uri = '/api/v2/collections';
            } else {
                $uri = '/api/v2/nodes';
            }

            $nodes = $this->fs->findNodesByFilterUser($deleted, $query, $offset, $limit);
            $pager = new Pager($this->node_decorator, $nodes, $attributes, $offset, $limit, $uri);
            $result = $pager->paging();

            return (new Response())->setCode(200)->setBody($result);
        }

        return $this->bulk($id, $p, function ($node) use ($attributes) {
            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node, $attributes),
            ];
        });
    }

    /**
     * @api {get} /api/v2/nodes/:id/parents Get parent nodes
     * @apiVersion 2.0.0
     * @apiName getParents
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get system attributes of all parent nodes. The hirarchy of all parent nodes is ordered in a
     * single level array beginning with the collection on the highest level.
     * @apiUse _getNode
     * @apiUse _nodeAttributes
     *
     * @apiParam (GET Parameter) {boolean} [self=true] Include requested collection itself at the end of the list (Will be ignored if the requested node is a file)
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/nodes/parents?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v2/nodes/parents?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted&pretty"
     * curl -XGET "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686/parents?pretty&self=1"
     * curl -XGET "https://SERVER/api/v2/nodes/parents?p=/absolute/path/to/my/node&self=1"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "id": "544627ed3c58891f058bbbaa",
     *      "name": "rootdir",
     *      "meta": {},
     *      "size": 1,
     *      "mime": "inode/directory",
     *      "created": "2007-08-31T16:47+00:00",
     *      "changed": "2007-08-31T16:47+00:00",
     *      "destroy": "2020-08-31T16:47+00:00",
     *      "share": false,
     *      "directory": true
     *  },
     *  {
     *      "id": "544627ed3c58891f058b46cc",
     *      "name": "parentdir",
     *      "meta": {},
     *      "size": 3,
     *      "mime": "inode/directory",
     *      "created": "2007-08-31T16:47+00:00",
     *      "changed": "2007-08-31T16:47+00:00",
     *      "share": false,
     *      "directory": true
     *  }
     * ]
     *
     * @param string $id
     * @param string $p
     */
    public function getParents(?string $id = null, ?string $p = null, array $attributes = [], bool $self = false): Response
    {
        $result = [];
        $request = $this->_getNode($id, $p);
        $parents = $request->getParents();

        if (true === $self && $request instanceof Collection) {
            $result[] = $this->node_decorator->decorate($request, $attributes);
        }

        foreach ($parents as $node) {
            $result[] = $this->node_decorator->decorate($node, $attributes);
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {patch} /api/v2/nodes/:id Change attributes
     * @apiVersion 2.0.0
     * @apiName patch
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Change attributes
     * @apiUse _getNodes
     * @apiUse _multiError
     *
     * @apiParam (GET Parameter) {string} [name] Rename node, the characters (\ < > : " / * ? |) (without the "()") are not allowed to use within a node name.
     * @apiParam (GET Parameter) {boolean} [readonly] Mark node as readonly
     * @apiParam (GET Parameter) {object} [filter] Custom collection filter (Collection only)
     * @apiParam (GET Parameter) {string} [meta.description] UTF-8 Text Description - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [meta.color] Color Tag - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [meta.author] Author - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [meta.mail] Mail contact address - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [meta.license] License - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [meta.copyright] Copyright string - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string[]} [meta.tags] Tags - Must be an array full of strings
     *
     * @apiExample (cURL) example:
     * curl -XPATCH "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686?name=example"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200
     *
     * @param array|string $id
     * @param array|string $p
     */
    public function patch(?string $name = null, ?array $meta = null, ?bool $readonly = null, ?array $filter = null, ?array $acl = null, ?string $id = null, ?string $p = null): Response
    {
        $attributes = compact('name', 'meta', 'readonly', 'filter', 'acl');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        return $this->bulk($id, $p, function ($node) use ($attributes) {
            foreach ($attributes as $attribute => $value) {
                switch ($attribute) {
                    case 'name':
                        $node->setName($value);

                    break;
                    case 'meta':
                        $node->setMetaAttributes($value);

                    break;
                    case 'readonly':
                        $node->setReadonly($value);

                    break;
                    case 'filter':
                        if ($node instanceof Collection) {
                            $node->setFilter($value);
                        }

                    break;
                    case 'acl':
                        $node->setAcl($value);

                    break;
                }
            }

            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node),
            ];
        });
    }

    /**
     * @api {post} /api/v2/nodes/:id/clone Clone node
     * @apiVersion 2.0.0
     * @apiName postClone
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Clone a node
     * @apiUse _getNode
     * @apiUse _conflictNode
     * @apiUse _multiError
     * @apiUse _writeAction
     *
     * @apiParam (GET Parameter) {string} [destid] Either destid or destp (path) of the new parent collection node must be given.
     * @apiParam (GET Parameter) {string} [destp] Either destid or destp (path) of the new parent collection node must be given.
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v2/nodes/clone?id=544627ed3c58891f058b4686&dest=544627ed3c58891f058b4676"
     * curl -XPOST "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686/clone?dest=544627ed3c58891f058b4676&conflict=2"
     * curl -XPOST "https://SERVER/api/v2/nodes/clone?p=/absolute/path/to/my/node&conflict=0&destp=/new/parent"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param array|string $id
     * @param array|string $id
     * @param array|string $p
     * @param string       $destid
     * @param string       $destp
     */
    public function postClone(
        $id = null,
        $p = null,
        ?string $destid = null,
        ?string $destp = null,
        int $conflict = 0
    ): Response {
        try {
            $parent = $this->_getNode($destid, $destp, Collection::class, false, true);
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound(
                'destination collection was not found or is not a collection',
                Exception\NotFound::DESTINATION_NOT_FOUND
            );
        }

        return $this->bulk($id, $p, function ($node) use ($parent, $conflict) {
            $result = $node->copyTo($parent, $conflict);

            return [
                'code' => $parent == $result ? 200 : 201,
                'data' => $this->node_decorator->decorate($result),
            ];
        });
    }

    /**
     * @api {post} /api/v2/nodes/:id/move Move node
     * @apiVersion 2.0.0
     * @apiName postMove
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Move node
     * @apiUse _getNodes
     * @apiUse _conflictNode
     * @apiUse _multiError
     * @apiUse _writeAction
     *
     * @apiParam (GET Parameter) {string} [destid] Either destid or destp (path) of the new parent collection node must be given.
     * @apiParam (GET Parameter) {string} [destp] Either destid or destp (path) of the new parent collection node must be given.
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v2/nodes/move?id=544627ed3c58891f058b4686?destid=544627ed3c58891f058b4655"
     * curl -XPOST "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686/move?destid=544627ed3c58891f058b4655"
     * curl -XPOST "https://SERVER/api/v2/nodes/move?p=/absolute/path/to/my/node&destp=/new/parent&conflict=1
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @apiSuccessExample {json} Success-Response (conflict=1):
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data": "renamed (xy23)"
     * }
     *
     * @param array|string $id
     * @param array|string $p
     * @param string       $destid
     * @param string       $destp
     */
    public function postMove(
        $id = null,
        $p = null,
        ?string $destid = null,
        ?string $destp = null,
        int $conflict = 0
    ): Response {
        try {
            $parent = $this->_getNode($destid, $destp, Collection::class, false, true);
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound(
                'destination collection was not found or is not a collection',
                Exception\NotFound::DESTINATION_NOT_FOUND
            );
        }

        return $this->bulk($id, $p, function ($node) use ($parent, $conflict) {
            $result = $node->setParent($parent, $conflict);

            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node),
            ];
        });
    }

    /**
     * @api {delete} /api/v2/nodes/:id Delete node
     * @apiVersion 2.0.0
     * @apiName delete
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Delete node
     * @apiUse _getNodes
     * @apiUse _multiError
     * @apiUse _writeAction
     *
     * @apiParam (GET Parameter) {boolean} [force=false] Force flag need to be set to delete a node from trash (node must have the deleted flag)
     * @apiParam (GET Parameter) {boolean} [ignore_flag=false] If both ignore_flag and force_flag were set, the node will be deleted completely
     * @apiParam (GET Parameter) {number} [at] Has to be a valid unix timestamp if so the node will destroy itself at this specified time instead immediatly
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v2/node?id=544627ed3c58891f058b4686"
     * curl -XDELETE "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686?force=1&ignore_flag=1"
     * curl -XDELETE "https://SERVER/api/v2/node?p=/absolute/path/to/my/node"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param array|string $id
     * @param array|string $p
     * @param int          $at
     */
    public function delete(
        $id = null,
        $p = null,
        bool $force = false,
        bool $ignore_flag = false,
        ?string $at = null
    ): Response {
        $failures = [];

        if (null !== $at && '0' !== $at) {
            $at = $this->_verifyAttributes(['destroy' => $at])['destroy'];
        }

        return $this->bulk($id, $p, function ($node) use ($force, $ignore_flag, $at) {
            if (null === $at) {
                $node->delete($force && $node->isDeleted() || $force && $ignore_flag);
            } else {
                if ('0' === $at) {
                    $at = null;
                }
                $node->setDestroyable($at);
            }

            return [
                'code' => 204,
            ];
        });
    }

    /**
     * @api {get} /api/v2/nodes/trash Get trash
     * @apiName getTrash
     * @apiVersion 2.0.0
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription A similar endpoint to /api/v2/nodes/query filer={'deleted': {$type: 9}] but instead returning all deleted
     * nodes (including children which are deleted as well) this enpoint only returns the first deleted node from every subtree)
     * @apiUse _nodeAttributes
     *
     * @apiExample (cURL) example:
     * curl -XGET https://SERVER/api/v2/nodes/trash?pretty
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter node attributes
     *
     * @apiSuccess (200 OK) {object[]} - List of deleted nodes
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *  }
     * ]
     */
    public function getTrash(array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        $children = [];
        $nodes = $this->fs->findNodesByFilterUser(NodeInterface::DELETED_ONLY, ['deleted' => ['$type' => 9]], $offset, $limit);

        foreach ($nodes as $node) {
            try {
                $parent = $node->getParent();
                if (null !== $parent && $parent->isDeleted()) {
                    continue;
                }
            } catch (\Exception $e) {
                //skip exception
            }

            $children[] = $node;
        }

        if ($this instanceof ApiFile) {
            $query['directory'] = false;
            $uri = '/api/v2/files';
        } elseif ($this instanceof ApiCollection) {
            $query['directory'] = true;
            $uri = '/api/v2/collections';
        } else {
            $uri = '/api/v2/nodes';
        }

        $pager = new Pager($this->node_decorator, $children, $attributes, $offset, $limit, $uri, $nodes->getReturn());
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/nodes/delta Get delta
     * @apiVersion 2.0.0
     * @apiName getDelta
     * @apiGroup Node
     * @apiPermission none
     * @apiUse _getNode
     *
     * @apiDescription Use this method to request a delta feed with all changes on the server (or) a snapshot of the server state.
     * since the state of the submited cursor. If no cursor was submited the server will create one which can than be used to request any further deltas.
     * If has_more is TRUE you need to request /delta immediatly again to
     * receive the next bunch of deltas. If has_more is FALSE you should wait at least 120s seconds before any further requests to the
     * api endpoint. You can also specify additional node attributes with the $attributes paramter or request the delta feed only for a specific node (see Get Attributes for that).
     * If reset is TRUE you have to clean your local state because you will receive a snapshot of the server state, it is the same as calling the /delta endpoint
     * without a cursor. reset could be TRUE if there was an account maintenance or a simialar case.
     * You can request a different limit as well but be aware that the number of nodes could be slighty different from your requested limit.
     * If requested with parameter id or p the delta gets generated recursively from the node given.
     *
     * @apiParam (GET Parameter) {number} [limit=250] Limit the number of delta entries, if too low you have to call this endpoint more often since has_more would be true more often
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes, per default not all attributes would be returned
     * @apiParam (GET Parameter) {string} [cursor=null] Set a cursor to rquest next nodes within delta processing
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/nodes/delta?pretty"
     *
     * @apiSuccess (200 OK) {boolean} reset If true the local state needs to be reseted, is alway TRUE during
     * the first request to /delta without a cursor or in special cases like server or account maintenance
     * @apiSuccess (200 OK) {string} cursor The cursor needs to be stored and reused to request further deltas
     * @apiSuccess (200 OK) {boolean} has_more If has_more is TRUE /delta can be requested immediatly after the last request
     * to receive further delta. If it is FALSE we should wait at least 120 seconds before any further delta requests to the api endpoint
     * @apiSuccess (200 OK) {object[]} nodes Node list to process
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "reset": false,
     *      "cursor": "aW5pdGlhbHwxMDB8NTc1YTlhMGIzYzU4ODkwNTE0OGI0NTZifDU3NWE5YTBiM2M1ODg5MDUxNDhiNDU2Yw==",
     *      "has_more": false,
     *       "nodes": [
     *          {
     *              "id": "581afa783c5889ad7c8b4572",
     *              "deleted": " 2008-08-31T16:47+00:00",
     *              "changed": "2007-08-31T16:47+00:00",
     *              "path": "\/AAA\/AL",
     *              "directory": true
     *          },
     *          {
     *              "id": "581afa783c5889ad7c8b3dcf",
     *              "created": "2007-08-31T16:47+00:00",
     *              "changed": "2007-09-28T12:33+00:00",
     *              "path": "\/AL",
     *              "directory": true
     *          }
     *      ]
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $cursor
     */
    public function getDelta(
        DeltaAttributeDecorator $delta_decorator,
        ?string $id = null,
        ?string $p = null,
        ?string $cursor = null,
        int $limit = 250,
        array $attributes = []
    ): Response {
        if (null !== $id || null !== $p) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }

        $result = $this->fs->getDelta()->getDeltaFeed($cursor, $limit, $node);
        foreach ($result['nodes'] as &$node) {
            if ($node instanceof NodeInterface) {
                $node = $this->node_decorator->decorate($node, $attributes);
            } else {
                $node = $delta_decorator->decorate($node, $attributes);
            }
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/nodes/:id/event-log Event log
     * @apiVersion 2.0.0
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
     * curl -XGET "https://SERVER/api/v2/nodes/event-log?pretty"
     * curl -XGET "https://SERVER/api/v2/nodes/event-log?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v2/nodes/544627ed3c58891f058b4686/event-log?pretty&limit=10"
     * curl -XGET "https://SERVER/api/v2/nodes/event-log?p=/absolute/path/to/my/node&pretty"
     *
     * @apiParam (GET Parameter) {number} [limit=100] Sets limit of events to be returned
     * @apiParam (GET Parameter) {number} [skip=0] How many events are skiped (useful for paging)
     *
     * @apiSuccess (200 OK) {object[]} - List of events
     * @apiSuccess (200 OK) {number} -.event Event ID
     * @apiSuccess (200 OK) {object} -.timestamp ISO8601 timestamp when the event occured
     * @apiSuccess (200 OK) {string} -.operation event operation (like addCollection, deleteFile, ...)
     * @apiSuccess (200 OK) {object} -.parent Parent node object at the time of the event
     * @apiSuccess (200 OK) {object} -.previous Previous state of actual data which has been modified during an event, can contain either version, name or parent
     * @apiSuccess (200 OK) {number} -.previous.version Version at the time before the event
     * @apiSuccess (200 OK) {string} -.previous.name Name at the time before the event
     * @apiSuccess (200 OK) {object} -.previous.parent Parent node object at the time before the event
     * @apiSuccess (200 OK) {object} -.share shared collection object at the time of the event (If the node was part of a share)
     * @apiSuccess (200 OK) {string} -.name Name of the node at the time of the event
     * @apiSuccess (200 OK) {object} -.node Current node object
     * @apiSuccess (200 OK) {object} -.user User who executed an event
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "id": "57628e523c5889026f8b4570",
     *      "timestamp": " 2018-01-02T13:22+00:00",
     *      "operation": "restoreFile",
     *      "name": "file.txt",
     *      "previous": {
     *          "version": 16
     *      },
     *      "node": {
     *          "id": "558c0b273c588963078b457a",
     *          "name": "3dddsceheckfile.txt",
     *          "deleted": false
     *      },
     *      "parent": null,
     *      "user": {
     *          "id": "54354cb63c58891f058b457f",
     *          "username": "example"
     *      }
     *  }
     * ]
     *
     * @param string $id
     * @param string $p
     */
    public function getEventLog(EventAttributeDecorator $event_decorator, ?string $id = null, ?string $p = null, ?array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if (null !== $id || null !== $p) {
            $node = $this->_getNode($id, $p);
            $uri = '/api/v2/nodes/'.$node->getId().'/event-log';
        } else {
            $node = null;
            $uri = '/api/v2/nodes/event-log';
        }

        $result = $this->fs->getDelta()->getEventLog($limit, $offset, $node, $total);
        $pager = new Pager($event_decorator, $result, $attributes, $offset, $limit, $uri, $total);

        return (new Response())->setCode(200)->setBody($pager->paging());
    }

    /**
     * @api {get} /api/v2/nodes/last-cursor Get last Cursor
     * @apiVersion 2.0.0
     * @apiName geLastCursor
     * @apiGroup Node
     * @apiUse _getNode
     * @apiPermission none
     * @apiDescription Use this method to request the latest cursor if you only need to now
     * if there are changes on the server. This method will not return any other data than the
     * newest cursor. To request a feed with all deltas request /delta.
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/nodes/last-cursor?pretty"
     *
     * @apiSuccess (200 OK) {string} cursor v2 cursor
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * "aW5pdGlhbHwxMDB8NTc1YTlhMGIzYzU4ODkwNTE0OGI0NTZifDU3NWE5YTBiM2M1ODg5MDUxNDhiNDU2Yw=="
     *
     * @param string $id
     * @param string $p
     */
    public function getLastCursor(?string $id = null, ?string $p = null): Response
    {
        if (null !== $id || null !== $p) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }

        $result = $this->fs->getDelta()->getLastCursor();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Merge multiple nodes into one zip archive.
     *
     * @param string $id
     * @param string $path
     */
    protected function combine($id = null, $path = null, string $name = 'selected')
    {
        $archive = new ZipStream($name.'.zip');

        foreach ($this->_getNodes($id, $path) as $node) {
            try {
                $node->zip($archive);
            } catch (\Exception $e) {
                $this->logger->debug('failed zip node in multi node request ['.$node->getId().']', [
                   'category' => get_class($this),
                   'exception' => $e,
               ]);
            }
        }

        $archive->finish();
    }

    /**
     * Check custom node attributes which have to be written.
     */
    protected function _verifyAttributes(array $attributes): array
    {
        $valid_attributes = [
            'changed',
            'destroy',
            'created',
            'meta',
            'readonly',
            'acl',
        ];

        if ($this instanceof ApiCollection) {
            $valid_attributes += ['filter', 'mount'];
        }

        $check = array_merge(array_flip($valid_attributes), $attributes);

        if ($this instanceof ApiCollection && count($check) > 8) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, acl, filter, mount, readonly and/or meta attributes may be overwritten');
        }
        if ($this instanceof ApiFile && count($check) > 6) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, acl, readonly and/or meta attributes may be overwritten');
        }

        foreach ($attributes as $attribute => $value) {
            switch ($attribute) {
                case 'filter':
                    if (!is_array($value)) {
                        throw new Exception\InvalidArgument($attribute.' must be an array');
                    }

                    $attributes['filter'] = json_encode($value);

                break;
                case 'destroy':
                    if (!Helper::isValidTimestamp($value)) {
                        throw new Exception\InvalidArgument($attribute.' timestamp must be valid unix timestamp');
                    }
                    $attributes[$attribute] = new UTCDateTime($value.'000');

                break;
                case 'changed':
                case 'created':
                    if (!Helper::isValidTimestamp($value)) {
                        throw new Exception\InvalidArgument($attribute.' timestamp must be valid unix timestamp');
                    }
                    if ((int) $value > time()) {
                        throw new Exception\InvalidArgument($attribute.' timestamp can not be set greater than the server time');
                    }
                    $attributes[$attribute] = new UTCDateTime($value.'000');

                break;
                case 'readonly':
                    $attributes['readonly'] = (bool) $attributes['readonly'];

                break;
            }
        }

        return $attributes;
    }
}
