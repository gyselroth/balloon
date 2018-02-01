<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\Controller;
use Balloon\App\Api\Latest\Collection as ApiCollection;
use Balloon\App\Api\Latest\File as ApiFile;
use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Helper;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;
use Closure;
use Generator;
use Micro\Http\Response;
use MongoDB\BSON\UTCDateTime;
use PHPZip\Zip\Stream\ZipStream;
use Psr\Log\LoggerInterface;

class Node extends Controller
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
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Role decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    /**
     * Event decorator.
     *
     * @var EventAttributeDecorator
     */
    protected $event_decorator;

    /**
     * Initialize.
     *
     * @param Server             $server
     * @param AttributeDecorator $decorator
     * @param LoggerInterface    $logger
     */
    public function __construct(Server $server, AttributeDecorator $decorator, RoleAttributeDecorator $role_decorator, EventAttributeDecorator $event_decorator, LoggerInterface $logger)
    {
        $this->fs = $server->getFilesystem();
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->decorator = $decorator;
        $this->logger = $logger;
        $this->role_decorator = $role_decorator;
        $this->event_decorator = $event_decorator;
        $this->registerv1Attributes();
    }

    /**
     * @api {head} /api/v1/node?id=:id Node exists?
     * @apiVersion 1.0.0
     * @apiName head
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Check if a node exists. Per default deleted nodes are ignore which means it will
     *  return a 404 if a deleted node is requested. You can change this behaviour via the deleted parameter.
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XHEAD "https://SERVER/api/v1/node?id=544627ed3c58891f058b4686"
     * curl -XHEAD "https://SERVER/api/v1/node/544627ed3c58891f058b4686"
     * curl -XHEAD "https://SERVER/api/v1/node?p=/absolute/path/to/my/node"
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
     * @param int    $deleted
     *
     * @return Response
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
     * @api {post} /api/v1/node/undelete?id=:id Undelete node
     * @apiVersion 1.0.0
     * @apiName postUndelete
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Undelete (Apiore from trash) a single node or multiple ones.
     * @apiUse _getNodes
     * @apiUse _conflictNode
     * @apiUse _multiError
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/node/undelete?id[]=544627ed3c58891f058b4686&id[]=544627ed3c58891f058b46865&pretty"
     * curl -XPOST "https://SERVER/api/v1/node/undelete?id=544627ed3c58891f058b4686?pretty"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/undelete?conflict=2"
     * curl -XPOST "https://SERVER/api/v1/node/undelete?p=/absolute/path/to/my/node&conflict=0&move=1&destid=544627ed3c58891f058b46889"
     *
     * @apiParam (GET Parameter) {string} [destid] Either destid or destp (path) of the new parent collection node must be given.
     * @apiParam (GET Parameter) {string} [destp] Either destid or destp (path) of the new parent collection node must be given.
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @apiSuccessExample {json} Success-Response (conflict=1):
     * HTTP/1.1 200 OK
     * {
     *      "status":200,
     *      "data": "renamed (xy23)"
     *      }
     * }
     *
     * @param array|string $id
     * @param array|string $p
     * @param bool         $move
     * @param string       $destid
     * @param string       $destp
     * @param int          $conflict
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

            if (true === $move && NodeInterface::CONFLICT_RENAME === $conflict) {
                return [
                    'code' => 200,
                    'data' => [
                    ],
                ];
            }

            return ['code' => 204];
        });
    }

    /**
     * @api {get} /api/v1/node?id=:id Download stream
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Download node contents. Collections (Folder) are converted into
     * a zip file in realtime.
     * @apiUse _getNode
     *
     * @apiParam (GET Parameter) {number} [offset=0] Get content from a specific offset in bytes
     * @apiParam (GET Parameter) {number} [length=0] Get content with until length in bytes reached
     * @apiParam (GET Parameter) {string} [encode] Can be set to base64 to encode content as base64.
     * @apiParam (GET Parameter) {boolean} [download=false] Force download file (Content-Disposition: attachment HTTP header)
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node?id=544627ed3c58891f058b4686" > myfile.txt
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686" > myfile.txt
     * curl -XGET "https://SERVER/api/v1/node?p=/absolute/path/to/my/collection" > folder.zip
     *
     * @apiSuccessExample {string} Success-Response (encode=base64):
     * HTTP/1.1 200 OK
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
     * @param int          $offset
     * @param int          $legnth
     * @param string       $encode
     * @param bool         $download
     * @param string       $name
     */
    public function get(
        $id = null,
        $p = null,
        int $offset = 0,
        int $length = 0,
        ?string $encode = null,
        bool $download = false,
        string $name = 'selected'
    ): ?Response {
        if (is_array($id) || is_array($p)) {
            return $this->combine($id, $p, $name);
        }

        $node = $this->_getNode($id, $p);
        if ($node instanceof Collection) {
            return (new Response())->setBody(function () use ($node) {
                $node->getZip();
            });
        }

        $response = new Response();

        if (true === $download) {
            $response->setHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\''.rawurlencode($name));
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Length', (string) $node->getSize());
            $response->setHeader('Content-Transfer-Encoding', 'binary');
        } else {
            $response->setHeader('Content-Disposition', 'inline; filename*=UTF-8\'\''.rawurlencode($name));
        }

        return (new Response())
          ->setOutputFormat(null)
          ->setBody(function () use ($node, $encode, $offset, $length) {
              $mime = $node->getContentType();
              $stream = $node->get();
              $name = $node->getName();

              if (null === $stream) {
                  return;
              }

              if (0 !== $offset) {
                  if (fseek($stream, $offset) === -1) {
                      throw new Exception\Conflict(
                        'invalid offset requested',
                        Exception\Conflict::INVALID_OFFSET
                    );
                  }
              }

              $read = 0;
              header('Content-Type: '.$mime.'');
              if ('base64' === $encode) {
                  header('Content-Encoding: base64');
                  while (!feof($stream)) {
                      if (0 !== $length && $read + 8192 > $length) {
                          echo base64_encode(fread($stream, $length - $read));
                          exit();
                      }

                      echo base64_encode(fread($stream, 8192));
                      $read += 8192;
                  }
              } else {
                  while (!feof($stream)) {
                      if (0 !== $length && $read + 8192 > $length) {
                          echo fread($stream, $length - $read);
                          exit();
                      }

                      echo fread($stream, 8192);
                      $read += 8192;
                  }
              }
          });
    }

    /**
     * @api {post} /api/v1/node/readonly?id=:id Mark node as readonly
     * @apiVersion 1.0.0
     * @apiName postReadonly
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Mark (or unmark) node as readonly
     * @apiUse _getNodes
     * @apiUse _multiError
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/node/readonly?id[]=544627ed3c58891f058b4686&id[]=544627ed3c58891f058b46865&readonly=1"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/readonly?readonly=0"
     * curl -XPOST "https://SERVER/api/v1/node/readonly?p=/absolute/path/to/my/node"
     *
     * @apiParam (GET Parameter) {bool} [readonly=true] Set readonly to false to make node writeable again
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param array|string $id
     * @param array|string $p
     *
     * @return Response
     */
    public function postReadonly($id = null, $p = null, bool $readonly = true): Response
    {
        return $this->bulk($id, $p, function ($node) use ($readonly) {
            $node->setReadonly($readonly);

            return ['code' => 204];
        });
    }

    /**
     * @apiDefine _nodeAttributes
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
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes, per default not all attributes would be returned
     *
     * @param null|mixed $id
     * @param null|mixed $p
     */

    /**
     * @api {get} /api/v1/node/attributes?id=:id Get attributes
     * @apiVersion 1.0.0
     * @apiName getAttributes
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
     *          "directory": false
     *      }
     * }
     *
     * @param array|string $id
     * @param array|string $p
     * @param array        $attributes
     *
     * @return Response
     */
    public function getAttributes($id = null, $p = null, array $attributes = []): Response
    {
        if (is_array($id) || is_array($p)) {
            $nodes = [];
            foreach ($this->_getNodes($id, $p) as $node) {
                $nodes[] = $this->decorator->decorate($node, $attributes);
            }

            return (new Response())->setCode(200)->setBody([
                'code' => 200,
                'data' => $nodes,
            ]);
        }

        $result = $this->decorator->decorate($this->_getNode($id, $p), $attributes);

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * @api {get} /api/v1/node/parents?id=:id Get parent nodes
     * @apiVersion 1.0.0
     * @apiName getParents
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get system attributes of all parent nodes. The hirarchy of all parent nodes is ordered in a
     * single level array beginning with the collection on the highest level.
     * @apiUse _getNode
     * @apiUse _nodeAttributes
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
    public function getParents(?string $id = null, ?string $p = null, array $attributes = [], bool $self = false): Response
    {
        $request = $this->_getNode($id, $p);
        $parents = $request->getParents();
        $result = [];

        if (true === $self && $request instanceof Collection) {
            $result[] = $this->decorator->decorate($request, $attributes);
        }

        foreach ($parents as $node) {
            $result[] = $this->decorator->decorate($node, $attributes);
        }

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * @api {post} /api/v1/node/meta-attributes?id=:id Change meta attributes
     * @apiVersion 1.0.0
     * @apiName postMetaAttributes
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Change meta attributes of a node
     * @apiUse _getNodes
     * @apiUse _multiError
     *
     * @apiParam (GET Parameter) {string} [attributes.description] UTF-8 Text Description - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [attributes.color] Color Tag - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [attributes.author] Author - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [attributes.mail] Mail contact address - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [attributes.license] License - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string} [attributes.copyright] Copyright string - Can contain anything as long as it is a string
     * @apiParam (GET Parameter) {string[]} [attributes.tags] Tags - Must be an array full of strings
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/node/meta-attributes?id=544627ed3c58891f058b4686&author=peter.meier"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/meta-attributes?author=example"
     * curl -XPOST "https://SERVER/api/v1/node/meta-attributes?p=/absolute/path/to/my/node?license=GPL-3.0"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param array|string $id
     * @param array|string $p
     *
     * @return Response
     */
    public function postMetaAttributes(array $attributes, ?string $id = null, ?string $p = null): Response
    {
        return $this->bulk($id, $p, function ($node) use ($attributes) {
            $node->setMetaAttributes($attributes);

            return ['code' => 204];
        });
    }

    /**
     * @api {post} /api/v1/node/name?id=:id Rename node
     * @apiVersion 1.0.0
     * @apiName postName
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Rename a node. The characters (\ < > : " / * ? |) (without the "()") are not allowed to use within a node name.
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiParam (GET Parameter) {string} [name] The new name of the node
     * @apiError (Error 400) Exception name contains invalid characters
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/node/name?id=544627ed3c58891f058b4686&name=newname.txt"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4677/name?name=newdir"
     * curl -XPOST "https://SERVER/api/v1/node/name?p=/absolute/path/to/my/node&name=newname.txt"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     * @param string $name
     *
     * @return Response
     */
    public function postName(string $name, ?string $id = null, ?string $p = null): Response
    {
        $this->_getNode($id, $p)->setName($name);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v1/node/clone?id=:id Clone node
     * @apiVersion 1.0.0
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
     * curl -XPOST "https://SERVER/api/v1/node/clone?id=544627ed3c58891f058b4686&dest=544627ed3c58891f058b4676"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/clone?dest=544627ed3c58891f058b4676&conflict=2"
     * curl -XPOST "https://SERVER/api/v1/node/clone?p=/absolute/path/to/my/node&conflict=0&destp=/new/parent"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param array|string $id
     * @param array|string $p
     * @param string       $destid
     * @param string       $destp
     * @param int          $conflict
     *
     * @return Response
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
                'code' => 201,
                'data' => $result,
            ];
        });
    }

    /**
     * @api {post} /api/v1/node/move?id=:id Move node
     * @apiVersion 1.0.0
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
     * curl -XPOST "https://SERVER/api/v1/node/move?id=544627ed3c58891f058b4686?destid=544627ed3c58891f058b4655"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/move?destid=544627ed3c58891f058b4655"
     * curl -XPOST "https://SERVER/api/v1/node/move?p=/absolute/path/to/my/node&destp=/new/parent&conflict=1
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
     * @param int          $conflict
     *
     * @return Response
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
            if (NodeInterface::CONFLICT_RENAME === $conflict) {
                return [
                    'code' => 200,
                    'data' => $node->getName(),
                ];
            }

            return [
                'code' => 204,
            ];
        });
    }

    /**
     * @api {delete} /api/v1/node?id=:id Delete node
     * @apiVersion 1.0.0
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
     * curl -XDELETE "https://SERVER/api/v1/node?id=544627ed3c58891f058b4686"
     * curl -XDELETE "https://SERVER/api/v1/node/544627ed3c58891f058b4686?force=1&ignore_flag=1"
     * curl -XDELETE "https://SERVER/api/v1/node?p=/absolute/path/to/my/node"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param array|string $id
     * @param array|string $p
     * @param bool         $force
     * @param bool         $ignore_flag
     * @param int          $at
     *
     * @return Response
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
     * @apiUse _nodeAttributes
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
    public function getQuery(int $deleted = 0, array $filter = [], array $attributes = []): Response
    {
        $children = [];
        $nodes = $this->fs->findNodesByFilterUser($deleted, $filter);

        foreach ($nodes as $node) {
            $child = $this->decorator->decorate($node, $attributes);
            $children[] = $child;
        }

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $children,
        ]);
    }

    /**
     * @api {get} /api/v1/node/trash Get trash
     * @apiName getTrash
     * @apiVersion 1.0.0
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription A similar endpoint to /api/v1/node/query filer={'deleted': {$type: 9}] but instead returning all deleted
     * nodes (including children which are deleted as well) this enpoint only returns the first deleted node from every subtree)
     * @apiUse _nodeAttributes
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
    public function getTrash(array $attributes = []): Response
    {
        $children = [];
        $nodes = $this->fs->findNodesByFilterUser(NodeInterface::DELETED_ONLY, ['deleted' => ['$type' => 9]]);

        foreach ($nodes as $node) {
            try {
                $parent = $node->getParent();
                if (null !== $parent && $parent->isDeleted()) {
                    continue;
                }
            } catch (\Exception $e) {
            }

            $child = $this->decorator->decorate($node, $attributes);
            $children[] = $child;
        }

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => array_values($children),
        ]);
    }

    /**
     * @api {get} /api/v1/node/delta Get delta
     * @apiVersion 1.0.0
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
     * curl -XGET "https://SERVER/api/v1/node/delta?pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object} data Delta feed
     * @apiSuccess (200 OK) {boolean} data.reset If true the local state needs to be reseted, is alway TRUE during
     * the first request to /delta without a cursor or in special cases like server or account maintenance
     * @apiSuccess (200 OK) {string} data.cursor The cursor needs to be stored and reused to request further deltas
     * @apiSuccess (200 OK) {boolean} data.has_more If has_more is TRUE /delta can be requested immediatly after the last request
     * to receive further delta. If it is FALSE we should wait at least 120 seconds before any further delta requests to the api endpoint
     * @apiSuccess (200 OK) {object[]} data.nodes Node list to process
     * @apiSuccess (200 OK) {string} data.nodes.id Node ID
     * @apiSuccess (200 OK) {string} data.nodes.deleted Is node deleted?
     * @apiSuccess (200 OK) {object} data.nodes.changed Changed timestamp
     * @apiSuccess (200 OK) {number} data.nodes.changed.sec Unix timestamp
     * @apiSuccess (200 OK) {number} data.nodes.changed.usec Additional Microsecconds to Unix timestamp
     * @apiSuccess (200 OK) {object} data.nodes.created Created timestamp (If data.nodes[].deleted is TRUE, created will be NULL)
     * @apiSuccess (200 OK) {number} data.nodes.created.sec Unix timestamp
     * @apiSuccess (200 OK) {number} data.nodes.created.usec Additional Microsecconds to Unix timestamp
     * @apiSuccess (200 OK) {string} data.nodes.path The full absolute path to the node
     * @apiSuccess (200 OK) {string} data.nodes.directory Is true if node is a directory
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": {
     *          "reset": false,
     *          "cursor": "aW5pdGlhbHwxMDB8NTc1YTlhMGIzYzU4ODkwNTE0OGI0NTZifDU3NWE5YTBiM2M1ODg5MDUxNDhiNDU2Yw==",
     *          "has_more": false,
     *          "nodes": [
     *             {
     *                  "id": "581afa783c5889ad7c8b4572",
     *                  "deleted": true,
     *                  "created": null,
     *                  "changed": {
     *                      "sec": 1478163064,
     *                      "usec": 31.0.0
     *                  },
     *                  "path": "\/AAA\/AL",
     *                  "directory": true
     *              },
     *              {
     *                  "id": "581afa783c5889ad7c8b3dcf",
     *                  "deleted": false,
     *                  "created": {
     *                      "sec": 1478163048,
     *                      "usec": 101000
     *                  },
     *                  "changed": {
     *                      "sec": 1478163048,
     *                      "usec": 101000
     *                  },
     *                  "path": "\/AL",
     *                  "directory": true
     *              }
     *          ]
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $cursor
     * @param int    $limit
     * @param array  $attributes
     *
     * @return Response
     */
    public function getDelta(
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

        $result = $this->fs->getDelta()->getDeltaFeed($cursor, $limit, $attributes, $node);

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
    }

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
        if (null !== $id || null !== $p) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }

        $this->event_decorator->addDecorator('timestamp', function (array $event) {
            return Helper::dateTimeToUnix($event['timestamp']);
        });

        $result = $this->fs->getDelta()->getEventLog($limit, $skip, $node);
        $body = [];
        foreach ($result as $event) {
            $body[] = $this->event_decorator->decorate($event);
        }

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $body,
        ]);
    }

    /**
     * @api {get} /api/v1/node/last-cursor Get last Cursor
     * @apiVersion 1.0.0
     * @apiName geLastCursor
     * @apiGroup Node
     * @apiUse _getNode
     * @apiPermission none
     * @apiDescription Use this method to request the latest cursor if you only need to now
     * if there are changes on the server. This method will not return any other data than the
     * newest cursor. To request a feed with all deltas request /delta.
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/last-cursor?pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {string} data Newest cursor
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": "aW5pdGlhbHwxMDB8NTc1YTlhMGIzYzU4ODkwNTE0OGI0NTZifDU3NWE5YTBiM2M1ODg5MDUxNDhiNDU2Yw=="
     * }
     *
     * @param string $id
     * @param string $p
     *
     * @return Response
     */
    public function getLastCursor(?string $id = null, ?string $p = null): Response
    {
        if (null !== $id || null !== $p) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }

        $result = $this->fs->getDelta()->getLastCursor();

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * Do bulk operations.
     *
     * @param array|string $id
     * @param array|string $p
     * @param Closure      $action
     */
    protected function bulk($id, $p, Closure $action): Response
    {
        if (is_array($id) || is_array($p)) {
            $errors = [];
            $body = [];

            foreach ($this->_getNodes($id, $p) as $node) {
                try {
                    $body[] = $action->call($this, $node);
                } catch (\Exception $e) {
                    $errors[] = [
                        'error' => get_class($e),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ];
                }
            }

            if (!empty($errors)) {
                return (new Response())->setCode(400)->setBody([
                    'code' => 400,
                    'data' => $errors,
                ]);
            }
            if (empty($body)) {
                return (new Response())->setCode(204);
            }
            $body = array_shift($body);
            $response = (new Response())->setCode($body['code']);

            if (isset($body['data'])) {
                $response->setBody([
                    'code' => $body['code'],
                    'data' => $body['data'],
                ]);
            }

            return $response;
        }

        $body = $action->call($this, $this->_getNode($id, $p));
        $response = (new Response())->setCode($body['code']);

        if (isset($body['data'])) {
            $response->setBody([
                'code' => $body['code'],
                'data' => $body['data'],
            ]);
        }

        return $response;
    }

    /**
     * Downgrade latest api attributes to v1 attributes.
     */
    protected function registerv1Attributes(): void
    {
        $server = $this->server;
        $fs = $this->fs;

        $this->decorator->addDecorator('parent', function ($node) {
            $id = $node->getAttributes()['parent'];

            if (null === $id) {
                return null;
            }

            return (string) $id;
        });

        $this->decorator->addDecorator('shareowner', function ($node) use ($server, $fs) {
            if (!$node->isSpecial()) {
                return null;
            }

            try {
                return $server->getUserById($fs->findRawNode($node->getShareId())['owner'])->getUsername();
            } catch (\Exception $e) {
                return null;
            }
        });

        $this->decorator->addDecorator('history', function ($node) {
            if ($node instanceof File) {
                return $node->getHistory();
            }

            return null;
        });

        $this->decorator->addDecorator('filter', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('owner', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('shared', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('sharename', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('malware_quarantine', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('subscription', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('subscription_exclude_me', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('subscription_recursive', function ($node) {
            return null;
        });

        $this->decorator->addDecorator('share', function ($node) {
            return $node->isShare();
        });

        $this->decorator->addDecorator('created', function ($node) {
            return Helper::DateTimeToUnix($node->getAttributes()['created']);
        });

        $this->decorator->addDecorator('changed', function ($node) {
            return Helper::DateTimeToUnix($node->getAttributes()['changed']);
        });

        $this->decorator->addDecorator('deleted', function ($node) {
            return Helper::DateTimeToUnix($node->getAttributes()['deleted']);
        });

        $this->decorator->addDecorator('destroy', function ($node) {
            return Helper::DateTimeToUnix($node->getAttributes()['destroy']);
        });

        $this->decorator->addDecorator('filtered', function ($node) {
            if (!($node instanceof Collection)) {
                return null;
            }

            return $node->getAttributes()['filter'];
        });

        $this->role_decorator->addDecorator('username', function ($user) {
            return $user->getAttributes()['username'];
        });
    }

    /**
     * Get node.
     *
     * @param string $id
     * @param string $path
     * @param string $class      Force set node type
     * @param bool   $multiple   Allow $id to be an array
     * @param bool   $allow_root Allow instance of root collection
     * @param bool   $deleted    How to handle deleted node
     *
     * @return NodeInterface
     */
    protected function _getNode(
        ?string $id = null,
        ?string $path = null,
        ?string $class = null,
        bool $multiple = false,
        bool $allow_root = false,
        int $deleted = 2
    ): NodeInterface {
        if (null === $class) {
            switch (get_class($this)) {
                case ApiFile::class:
                    $class = File::class;

                break;
                case ApiCollection::class:
                    $class = Collection::class;

                break;
            }
        }

        return $this->fs->getNode($id, $path, $class, $multiple, $allow_root, $deleted);
    }

    /**
     * Get nodes.
     *
     * @param string $id
     * @param string $path
     * @param string $class   Force set node type
     * @param bool   $deleted How to handle deleted node
     *
     * @return Generator
     */
    protected function _getNodes(
        $id = null,
        $path = null,
        ?string $class = null,
        int $deleted = 2
    ): Generator {
        if (null === $class) {
            switch (get_class($this)) {
                case ApiFile::class:
                    $class = File::class;

                break;
                case ApiCollection::class:
                    $class = Collection::class;

                break;
            }
        }

        return $this->fs->getNodes($id, $path, $class, $deleted);
    }

    /**
     * Merge multiple nodes into one zip archive.
     *
     * @param string $id
     * @param string $path
     * @param string $name
     */
    protected function combine($id = null, $path = null, string $name = 'selected')
    {
        $temp = $this->server->getTempDir().DIRECTORY_SEPARATOR.'zip';
        if (!file_exists($temp)) {
            mkdir($temp, 0700, true);
        }

        ZipStream::$temp = $temp;
        $archive = new ZipStream($name.'.zip', 'application/zip', $name.'.zip');

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

        $archive->finalize();
    }

    /**
     * Check custom node attributes which have to be written.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function _verifyAttributes(array $attributes): array
    {
        $valid_attributes = [
            'changed',
            'destroy',
            'created',
            'meta',
            'readonly',
        ];

        if ($this instanceof ApiCollection) {
            $valid_attributes[] = 'filter';
        }

        $check = array_merge(array_flip($valid_attributes), $attributes);

        if ($this instanceof ApiCollection && count($check) > 6) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, filter, readonly and/or meta attributes may be overwritten');
        }
        if ($this instanceof ApiFile && count($check) > 5) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, readonly and/or meta attributes may be overwritten');
        }

        foreach ($attributes as $attribute => $value) {
            switch ($attribute) {
                case 'filter':
                    $attributes['filter'] = json_encode((array) $attributes['filter']);

                break;
                case 'destroy':
                    if (!Helper::isValidTimestamp($value)) {
                        throw new Exception\InvalidArgument($attribute.' Changed timestamp must be valid unix timestamp');
                    }
                    $attributes[$attribute] = new UTCDateTime($value.'000');

                break;
                case 'changed':
                case 'created':
                    if (!Helper::isValidTimestamp($value)) {
                        throw new Exception\InvalidArgument($attribute.' Changed timestamp must be valid unix timestamp');
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
