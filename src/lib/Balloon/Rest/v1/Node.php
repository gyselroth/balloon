<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest\v1;

use Balloon\Exception;
use Balloon\Controller;
use Balloon\Helper;
use Balloon\Filesystem\Node\INode;
use Balloon\Filesystem\Node\Root;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\Node as CoreNode;
use Balloon\Http\Response;
use Balloon\Rest\v1\Collection as RestCollection;
use Balloon\Rest\v1\File as RestFile;
use \PHPZip\Zip\Stream\ZipStream;

class Node extends Controller
{
    /**
     * @apiDefine _getNode
     *
     * @apiParam (GET Parameter) {string} id Either id or p (path) of a node must be given.
     * @apiParam (GET Parameter) {string} p Either id or p (path) of a node must be given.
     * @apiError (General Error Response) {number} status Status Code
     * @apiError (General Error Response) {object[]} data Error body
     * @apiError (General Error Response) {string} data.error Exception
     * @apiError (General Error Response) {string} data.message Message
     * @apiError (General Error Response) {number} data.code Error code
     *
     * @apiErrorExample {json} Error-Response (Invalid Parameter):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\InvalidArgument",
     *          "message": "invalid node id specified",
     *          "code": 0
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Insufficient Access):
     * HTTP/1.1 403 Forbidden
     * {
     *      "status": 403,
     *      "data": {
     *          "error": "Balloon\\Exception\\Forbidden",
     *          "message": "not allowed to read node 51354d073c58891f058b4580",
     *          "code": 40
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Not found):
     * HTTP/1.1 404 Not Found
     * {
     *      "status": 404,
     *      "data": {
     *          "error": "Balloon\\Exception\\NotFound",
     *          "message": "node 51354d073c58891f058b4580 not found",
     *          "code": 49
     *      }
     * }
     */

    
    /**
     * @apiDefine _multiError
     *
     * @apiErrorExample {json} Error-Response (Multi node error):
     * HTTP/1.1 400 Bad Request
     * {
     *     "status": 400,
     *     "data": [
     *         {
     *              id: "51354d073c58891f058b4580",
     *              name: "file.zip",
     *              error: "Balloon\\Exception\\Conflict",
     *              message: "node already exists",
     *              code: 30
     *         }
     *     ]
     * }
     */


    /**
     * @apiDefine _writeAction
     *
     * @apiErrorExample {json} Error-Response (Conflict):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "a node called myname does already exists",
     *          "code": 17
     *      }
     * }
     */


    /**
     * @apiDefine _conflictNode
     * @apiParam (GET Parameter) {number} [conflict=0] Decides how to handle a conflict if a node with the same name already exists at the destination.
     * Possible values are:</br>
     *  - 0 No action</br>
     *  - 1 Automatically rename the node</br>
     *  - 2 Overwrite the destination (merge)</br>
     */

    
    /**
     * @apiDefine _getNodes
     *
     * @apiParam (GET Parameter) {string[]} id Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.
     * @apiParam (GET Parameter) {string[]} p Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.
     * @apiError (General Error Response) {number} status Status Code
     * @apiError (General Error Response) {object[]} data Error body
     * @apiError (General Error Response) {string} data.error Exception
     * @apiError (General Error Response) {string} data.message Message
     * @apiError (General Error Response) {number} data.code General error messages of type  Balloon\\Exception do not usually have an error code
     *
     * @apiErrorExample {json} Error-Response (Invalid Parameter):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\InvalidArgument",
     *          "message": "invalid node id specified",
     *          "code": 0
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Insufficient Access):
     * HTTP/1.1 403 Forbidden
     * {
     *      "status": 403,
     *      "data": {
     *          "error": "Balloon\\Exception\\Forbidden",
     *          "message": "not allowed to read node 51354d073c58891f058b4580",
     *          "code": 40
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Not found):
     * HTTP/1.1 404 Not Found
     * {
     *      "status": 404,
     *      "data": {
     *          "error": "Balloon\\Exception\\NotFound",
     *          "message": "node 51354d073c58891f058b4580 not found",
     *          "code": 49
     *      }
     * }
     */


    /**
     * Get node
     *
     * @param   string $id
     * @param   string $path
     * @param   string $class Force set node type
     * @param   bool $deleted
     * @param   bool $multiple Allow $id to be an array
     * @param   bool $allow_root Allow instance of root collection
     * @param   bool $deleted How to handle deleted node
     * @return  INode
     */
    protected function _getNode(
        ?string $id=null,
        ?string $path=null,
        ?string $class=null,
        bool $multiple=false,
        bool $allow_root=false,
        int $deleted=2): INode
    {
        if ($class === null) {
            $class = join('', array_slice(explode('\\', get_class($this)), -1));
        }
        
        if ($class === 'Node') {
            $class = null;
        }

        return $this->fs->getNode($id, $path, $class, $multiple, $allow_root, $deleted);
    }
    

    /**
     * @api {head} /api/v1/node?id=:id Node exists?
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @param   int $deleted
     * @return  Response
     */
    public function head(?string $id=null, ?string $p=null, int $deleted=0): Response
    {
        try {
            $result = $this->_getNode($id, $p, null, false, false, $deleted);

            $response = (new Response())
                ->setHeader('Content-Length', (string)$result->getSize())
                ->setHeader('Content-Type', $result->getMime())
                ->setCode(200);
            return $response;
        } catch (\Exception $e) {
            return (new Response())->setCode(404);
        }
    }


    /**
     * @api {post} /api/v1/node/undelete?id=:id Undelete node
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @param   bool $move
     * @param   string $destid
     * @param   string $destp
     * @param   int $conflict
     * @return  void
     */
    public function postUndelete(
        $id=null,
        ?string $p=null,
        bool $move=false,
        ?string $destid=null,
        ?string $destp=null,
        int $conflict=0): Response
    {
        if ($move == true) {
            try {
                $parent = $this->_getNode($destid, $destp, 'Collection', false, true);
            } catch (Exception\NotFound $e) {
                throw new Exception\NotFound('destination collection was not found or is not a collection',
                    Exception\NotFound::DESTINATION_NOT_FOUND
                );
            }
        }
        
        if (is_array($id)) {
            $failures = [];
            foreach ($this->fs->findNodes($id) as $node) {
                try {
                    if ($move == true) {
                        $node = $node->setParent($parent, $conflict);
                    }

                    if ($node->isDeleted()) {
                        $node->undelete($conflict);
                    }
                } catch (\Exception $e) {
                    $failures[] = [
                        'id'      => (string)$node->getId(),
                        'name'    => $node->getName(),
                        'error'   => get_class($e),
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode()
                    ];

                    $this->logger->debug('failed undelete node in multi node request ['.$node->getId().']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            }

            if (empty($failures)) {
                return (new Response())->setCode(204);
            } else {
                return (new Response())->setCode(400)->setBody($failures);
            }
        } else {
            $node = $this->_getNode($id, $p);

            if ($move == true) {
                $node = $node->setParent($parent, $conflict);
            }

            if ($node->isDeleted()) {
                $result = $node->undelete($conflict);
            }

            if ($move == true && $conflict == INode::CONFLICT_RENAME) {
                return (new Response())->setCode(200)->setBody($node->getName());
            } else {
                return (new Response())->setCode(204);
            }
        }
    }


    /**
     * @api {post} /api/v1/node/share-link?id=:id Create sharing link
     * @apiVersion 1.0.6
     * @apiName postShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Create a unique sharing link of a node (global accessible):
     * a possible existing link will be deleted if this method will be called.
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiParam (POST Parameter) {object} [options] Sharing options
     * @apiParam (POST Parameter) {number} [options.expiration] Expiration unix timestamp of the sharing link
     * @apiParam (POST Parameter) {string} [options.password] Protected shared link with password
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686&pretty"
     * curl -XPOST "https://SERVER/api/v1/node/544627ed3c58891f058b4686/share-link?pretty"
     * curl -XPOST "https://SERVER/api/v1/node/share-link?p=/absolute/path/to/my/node&pretty"
     *
     * @apiSuccessExample {json} Success-Response (Created or modified share link):
     * HTTP/1.1 204 No Content
     *
     * @param   string $id
     * @param   string $p
     * @param   array $options
     * @return  Response
     */
    public function postShareLink(?string $id=null, ?string $p=null, array $options=[]): Response
    {
        $node = $this->_getNode($id, $p);
        $options = Helper::filter($options);
        $options['shared'] = true;

        $node->shareLink($options);
        return (new Response())->setCode(204);
    }


    /**
     * @api {delete} /api/v1/node/share-link?id=:id Delete sharing link
     * @apiVersion 1.0.6
     * @apiName deleteShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Delete an existing sharing link
     * @apiUse _getNode
     * @apiUse _writeAction
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686?pretty"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param   string $id
     * @param   string $p
     * @return  Response
     */
    public function deleteShareLink(?string $id=null, ?string $p=null): Response
    {
        $node = $this->_getNode($id, $p);
        
        $options = ['shared' => false];
        $node->shareLink($options);

        return (new Response())->setCode(204);
    }


    /**
     * @api {get} /api/v1/node/share-link?id=:id Get sharing link
     * @apiVersion 1.0.6
     * @apiName getShareLink
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get an existing sharing link
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v1/node/544627ed3c58891f058b4686/share-link?pretty"
     * curl -XGET "https://SERVER/api/v1/node/share-link?p=/path/to/my/node&pretty"
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {object} data Share options
     * @apiSuccess (200 OK) {string} data.token Shared unique node token
     * @apiSuccess (200 OK) {string} [data.password] Share link is password protected
     * @apiSuccess (200 OK) {string} [data.expiration] Unix timestamp
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *        "token": "544627ed3c51111f058b468654db6b7daca8e5.69846614",
     *     }
     * }
     *
     * @param   string $id
     * @param   string $p
     * @return  Response
     */
    public function getShareLink(?string $id=null, ?string $p=null): Response
    {
        $result = Helper::escape(
            $this->_getNode($id, $p)->getShareLink()
        );
        
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {get} /api/v1/node?id=:id Download stream
     * @apiVersion 1.0.6
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
     * @param   string $p
     * @param   int $offset
     * @param   int $legnth
     * @param   string $encode
     * @param   bool $download
     * @param   string $name
     * @return  void
     */
    public function get(
        $id=null,
        ?string $p=null,
        int $offset=0,
        int $length=0,
        ?string $encode=null,
        bool $download=false,
        string $name='selected'): void
    {
        if (is_array($id)) {
            $this->_combine($id, $p, $name);
        } else {
            $node = $this->_getNode($id, $p);

            if ($node instanceof Balloon\Collection) {
                $node->getZip();
            } else {
                $mime  = $node->getMime();
                $stream = $node->get();
                $name  = $node->getName();
            }
        }

        if ($download == true) {
            header('Content-Disposition: attachment; filename*=UTF-8\'\'' .rawurlencode($name));
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Content-Type: application/octet-stream');
            header('Content-Length: '.$node->getSize());
            header('Content-Transfer-Encoding: binary');
        } else {
            header('Content-Disposition: inline; filename*=UTF-8\'\'' .rawurlencode($name));
        }

        if ($stream === null) {
            exit();
        }

        if ($offset !== 0) {
            if (fseek($stream, $offset) === -1) {
                throw Exception\Conflict('invalid offset requested',
                    Exception\Conflict::INVALID_OFFSET
                );
            }
        }

        $read = 0;
        header('Content-Type: '.$mime.'');
        if ($encode === 'base64') {
            header('Content-Encoding: base64');
            while (!feof($stream)) {
                if ($length !== 0 && $read + 8192 > $length) {
                    echo base64_encode(fread($stream, $length - $read));
                    exit();
                }

                echo base64_encode(fread($stream, 8192));
                $read += 8192;
            }
        } else {
            while (!feof($stream)) {
                if ($length !== 0 && $read + 8192 > $length) {
                    echo fread($stream, $length - $read);
                    exit();
                }

                echo fread($stream, 8192);
                $read += 8192;
            }
        }
            
        exit();
    }


    /**
     * @api {post} /api/v1/node/readonly?id=:id Mark node as readonly
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @return  Response
     */
    public function postReadonly($id=null, ?string $p=null, bool $readonly=true): Response
    {
        if (is_array($id)) {
            $failures = [];
            foreach ($this->fs->findNodes($id) as $node) {
                try {
                    $node->setReadonly($readonly);
                } catch (\Exception $e) {
                    $failures[] = [
                        'id'      => (string)$node->getId(),
                        'name'    => $node->getName(),
                        'error'   => get_class($e),
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode()
                    ];

                    $this->logger->debug('failed set readonly node in multi node request ['.$node->getId().']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            }

            if (empty($failures)) {
                return (new Response())->setCode(204);
            } else {
                return (new Response())->setCode(400)->setBody($failures);
            }
        } else {
            $result = $this->_getNode($id, $p)->setReadonly($readonly);
            return (new Response())->setCode(204);
        }
    }


    /**
     * Merge multiple nodes into one zip archive
     *
     * @param   string $id
     * @param   string $path
     * @param   string $name
     * @return  void
     */
    protected function _combine($id=null, ?string $path=null, string $name='selected'): void
    {
        $temp = $this->config->dir->temp.DIRECTORY_SEPARATOR.'zip';
        if (!file_exists($temp)) {
            mkdir($temp, 0700, true);
        }

        ZipStream::$temp = $temp;
        $archive = new ZipStream($name.".zip", "application/zip", $name.".zip");
        
        foreach ($this->fs->findNodes($id) as $node) {
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
        exit();
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
     * @apiSuccess (200 OK - additional attributes) {object[]} data.history Get file history (file node only)
     *
     * @apiParam (GET Parameter) {string[]} [attributes] Filter attributes, per default not all attributes would be returned
     */


    /**
     * @api {get} /api/v1/node/attributes?id=:id Get attributes
     * @apiVersion 1.0.6
     * @apiName getAttributes
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get node attribute
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
     *          "thumbnail": "544628243c5889b86d8b4568",
     *          "directory": false
     *      }
     * }
     *
     * @param  string $id
     * @param  string $p
     * @param  array $attributes
     * @return Response
     */
    public function getAttributes(?string $id=null, ?string $p=null, array $attributes=[]): Response
    {
        $result = Helper::escape(
            $this->_getNode($id, $p)->getAttribute($attributes)
        );
        
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * Check custom node attributes which have to be written
     *
     * @param   array $attributes
     * @return  array
     */
    protected function _verifyAttributes(array $attributes): array
    {
        $valid_attributes = [
            'changed',
            'destroy',
            'created',
            'meta',
            'readonly'
        ];

        if ($this instanceof RestCollection) {
            $valid_attributes[] = 'filter';
        }

        $check = array_merge(array_flip($valid_attributes), $attributes);
        
        if ($this instanceof RestCollection && count($check) > 6) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, filter, readonly and/or meta attributes may be overwritten');
        } elseif ($this instanceof RestFile && count($check) > 5) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, readonly and/or meta attributes may be overwritten');
        }

        foreach ($attributes as $attribute => $value) {
            switch ($attribute) {
                case 'meta':
                    $attributes['meta'] = CoreNode::validateMetaAttribute($attributes['meta']);
                break;
            
                case 'filter':
                    $attributes['filter'] = (array)$attributes['filter'];
                break;
                   
                case 'destroy':
                    if (!Helper::isValidTimestamp($value)) {
                        throw new Exception\InvalidArgument($attribute.' Changed timestamp must be valid unix timestamp');
                    }
                    $attributes[$attribute] = new \MongoDB\BSON\UTCDateTime($value.'000');
                break;

                case 'changed':
                case 'created':
                    if (!Helper::isValidTimestamp($value)) {
                        throw new Exception\InvalidArgument($attribute.' Changed timestamp must be valid unix timestamp');
                    } elseif ((int)$value > time()) {
                        throw new Exception\InvalidArgument($attribute.' timestamp can not be set greater than the server time');
                    }
                    $attributes[$attribute] = new \MongoDB\BSON\UTCDateTime($value.'000');
                break;

                case 'readonly':
                    $attributes['readonly'] = (bool)$attributes['readonly'];
                break;
            }
        }

        return $attributes;
    }


    /**
     * @api {get} /api/v1/node/parent?id=:id Get parent node
     * @apiVersion 1.0.6
     * @apiName getParent
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Get system attributes of the parent node
     * @apiUse _getNode
     * @apiUse _nodeAttributes
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
     * @param  string $id
     * @param  string $p
     * @param  array $attributes
     * @return Response
     */
    public function getParent(?string $id=null, ?string $p=null, array $attributes=[]): Response
    {
        $result = Helper::escape(
            $this->_getNode($id, $p)
                 ->getParent()
                 ->getAttribute($attributes)
        );
        
        return (new Response())->setCode(200)->setBody($result);
    }

    
    /**
     * @api {get} /api/v1/node/parents?id=:id Get parent nodes
     * @apiVersion 1.0.6
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
                {
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
     * @param  string $id
     * @param  string $p
     * @param  array $attributes
     * @return Response
     */
    public function getParents(?string $id=null, ?string $p=null, array $attributes=[], bool $self=false): Response
    {
        $request = $this->_getNode($id, $p);
        $parents = $request->getParents();
        $result = [];
        
        if ($self === true && $request instanceof Collection) {
            $result[] = $request->getAttribute($attributes);
        }
        
        foreach ($parents as $node) {
            $result[] = $node->getAttribute($attributes);
        }

        $result = Helper::escape($result);
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {post} /api/v1/node/meta-attributes?id=:id Write meta attributes
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @return  Response
     */
    public function postMetaAttributes(?string $id=null, ?string $p=null): Response
    {
        $this->_getNode($id, $p)->setMetaAttribute(Helper::filter($_POST));
        return (new Response())->setCode(204);
    }

    
    /**
     * @api {post} /api/v1/node/name?id=:id Rename node
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @param   string $name
     * @return  Response
     */
    public function postName(string $name, ?string $id=null, ?string $p=null): Response
    {
        $this->_getNode($id, $p)->setName($name);
        return (new Response())->setCode(204);
    }
    

    /**
     * @api {post} /api/v1/node/clone?id=:id Clone node
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @param   string $destid
     * @param   string $destp
     * @param   int $conflict
     * @return  Response
     */
    public function postClone(
        $id=null,
        ?string $p=null,
        ?string $destid=null,
        ?string $destp=null,
        int $conflict=0): Response
    {
        try {
            $parent = $this->_getNode($destid, $destp, 'Collection', false, true);
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound('destination collection was not found or is not a collection',
                Exception\NotFound::DESTINATION_NOT_FOUND
            );
        }
        
        if (is_array($id)) {
            $failures = [];
            foreach ($this->fs->findNodes($id) as $node) {
                try {
                    $node->copyTo($parent, $conflict);
                } catch (\Exception $e) {
                    $failures[] = [
                        'id'      => (string)$node->getId(),
                        'name'    => $node->getName(),
                        'error'   => get_class($e),
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode()
                    ];

                    $this->logger->debug('failed clone node in multi node request ['.$node->getId().']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            };

            if (empty($failures)) {
                return (new Response())->setCode(204);
            } else {
                return(new Response())->setCode(400)->setBody($failures);
            }
        } else {
            $result = $this->_getNode($id, $p)->copyTo($parent, $conflict);
            return (new Response())->setCode(201)->setBody((string)$result->getId());
        }
    }


    /**
     * @api {post} /api/v1/node/move?id=:id Move node
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $id
     * @param   string $p
     * @param   string $destid
     * @param   string $destp
     * @param   int $conflict
     * @return  Response
     */
    public function postMove(
        $id=null,
        ?string $p=null,
        ?string $destid=null,
        ?string $destp=null,
        int $conflict=0): Response
    {
        try {
            $parent = $this->_getNode($destid, $destp, 'Collection', false, true);
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound('destination collection was not found or is not a collection',
                Exception\NotFound::DESTINATION_NOT_FOUND
            );
        }

        if (is_array($id)) {
            $failures = [];
            foreach ($this->fs->findNodes($id) as $node) {
                try {
                    $node->setParent($parent, $conflict);
                } catch (\Exception $e) {
                    $failures[] = [
                        'id'      => (string)$node->getId(),
                        'name'    => $node->getName(),
                        'error'   => get_class($e),
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode()
                    ];

                    $this->logger->debug('failed move node in multi node request ['.$node->getId().']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            }
            
            if (empty($failures)) {
                return (new Response())->setCode(204);
            } else {
                return (new Response())->setCode(400)->setBody($failures);
            }
        } else {
            $node   = $this->_getNode($id, $p);
            $result = $node->setParent($parent, $conflict);
            
            if ($conflict == INode::CONFLICT_RENAME) {
                return (new Response())->setCode(200)->setBody($node->getName());
            } else {
                return (new Response())->setCode(204);
            }
        }
    }


    /**
     * @api {delete} /api/v1/node?id=:id Delete node
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @param   bool $force
     * @param   bool $ignore_flag
     * @param   int $at
     * @return  Response
     */
    public function delete(
        $id=null,
        ?string $p=null,
        bool $force=false,
        bool $ignore_flag=false,
        ?string $at=null): Response
    {
        $failures = [];
            
        if ($at !== null && $at !== '0') {
            $at = $this->_verifyAttributes(['destroy' => $at])['destroy'];
        }

        if (is_array($id)) {
            foreach ($this->fs->findNodes($id) as $node) {
                try {
                    if ($at === null) {
                        $node->delete($force && $node->isDeleted() || $force && $ignore_flag);
                    } else {
                        if ($at === '0') {
                            $at = null;
                        }
                        $node->setDestroyable($at);
                    }
                } catch (\Exception $e) {
                    $failures[] = [
                        'id'      => (string)$node->getId(),
                        'name'    => $node->getName(),
                        'error'   => get_class($e),
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode()
                    ];

                    $this->logger->debug('failed delete node in multi node request ['.$node->getId().']', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);
                }
            }
            
            if (empty($failures)) {
                return (new Response())->setCode(204);
            } else {
                return (new Response())->setcode(400)->setBody($failures);
            }
        } else {
            if ($at === null) {
                $result = $this->_getNode($id, $p)->delete($force);
            } else {
                if ($at === '0') {
                    $at = null;
                }
                
                $result = $this->_getNode($id, $p)->setDestroyable($at);
            }

            return (new Response())->setCode(204);
        }
    }


    /**
     * @api {get} /api/v1/node/query Custom query
     * @apiVersion 1.0.6
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
     * @param  int $deleted
     * @param  array $filter
     * @param  array $attributes
     * @return Response
     */
    public function getQuery(int $deleted=0, array $filter=[], array $attributes=[]): Response
    {
        $children = [];
        $nodes = $this->fs->findNodesWithCustomFilterUser($deleted, $filter);
        
        foreach ($nodes as $node) {
            $child = Helper::escape($node->getAttribute($attributes));
            $children[] = $child;
        }
        
        return (new Response())->setCode(200)->setBody($children);
    }

    
    /**
     * @api {get} /api/v1/node/trash Get trash
     * @apiName getTrash
     * @apiVersion 1.0.6
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
     * @param  array $attributes
     * @return Response
     */
    public function getTrash(array $attributes=[]): Response
    {
        $children = [];
        $nodes = $this->fs->findNodesWithCustomFilterUser(INode::DELETED_ONLY, ['deleted' => ['$type' => 9]]);

        foreach ($nodes as $node) {
            try {
                $parent = $node->getParent();
                if ($parent !== null && $parent->isDeleted()) {
                    continue;
                }
            } catch (\Exception $e) {
            }

            $child = Helper::escape($node->getAttribute($attributes));
            $children[] = $child;
        }
        
        return (new Response())->setCode(200)->setBody(array_values($children));
    }


    /**
     * @api {get} /api/v1/node/search Search
     * @apiVersion 1.0.6
     * @apiName getSearch
     * @apiGroup Node
     * @apiPermission none
     * @apiDescription Extended search query, using the integrated search engine (elasticsearch).
     * @apiUse _nodeAttributes
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
     * @param  array $query
     * @param  array $attributes
     * @param  int $deleted
     * @return Response
     */
    public function getSearch(array $query, array $attributes=[], int $deleted=0): Response
    {
        $children = [];
        $nodes = $this->fs->search($query, $deleted);

        foreach ($nodes as $node) {
            try {
                $child = Helper::escape($node->getAttribute($attributes));
                $children[] = $child;
            } catch (\Exception $e) {
                $this->logger->info('error occured during loading attributes, skip search result node', [
                    'category' => get_class($this),
                    'exception' => $e
                ]);
            }
        }
 
        return (new Response())->setCode(200)->setBody($children);
    }


    /**
     * @api {get} /api/v1/node/delta Get delta
     * @apiVersion 1.0.6
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
     *                      "usec": 317000
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
     * @param   string $id
     * @param   string $p
     * @param   string $cursor
     * @param   int $limit
     * @param   array $attributes
     * @return  Response
     */
    public function getDelta(
        ?string $id=null,
        ?string $p=null,
        ?string $cursor=null,
        int $limit=250,
        array $attributes=[]): Response
    {
        if ($id !== null || $p !== null) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }
        
        $result= $this->fs->getDelta()->getDeltaFeed($cursor, $limit, $attributes, $node);

        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {get} /api/v1/node/event-log?id=:id Event log
     * @apiVersion 1.0.6
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
     * @param  string $id
     * @param  string $p
     * @param  int $skip
     * @param  int $limit
     * @return Response
     */
    public function getEventLog(?string $id=null, ?string $p=null, int $skip=0, int $limit=100): Response
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
     * @api {get} /api/v1/node/last-cursor Get last Cursor
     * @apiVersion 1.0.6
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
     * @param   string $id
     * @param   string $p
     * @return  Response
     */
    public function getLastCursor(?string $id=null, ?string $p=null): Response
    {
        if ($id !== null || $p !== null) {
            $node = $this->_getNode($id, $p);
        } else {
            $node = null;
        }

        $result= $this->fs->getDelta()->getLastCursor();
        return (new Response())->setCode(200)->setBody($result);
    }
}
