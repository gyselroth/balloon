<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Files extends Nodes
{
    /**
     * @api {get} /api/v2/files/:id/history Get history
     * @apiVersion 2.0.0
     * @apiName getHistory
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Get a full change history of a file
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/files/history?id=544627ed3c58891f058b4686&pretty"
     * curl -XGET "https://SERVER/api/v2/files/544627ed3c58891f058b4686/history?pretty"
     * curl -XGET "https://SERVER/api/v2/files/history?p=/absolute/path/to/my/file&pretty"
     *
     * @apiSuccess (200 OK) {object[]} - History
     * @apiSuccess (200 OK) {number} -.version Version
     * @apiSuccess (200 OK) {string} -.changed ISO806 timestamp
     * @apiSuccess (200 OK) {object} -.user User object
     * @apiSuccess (200 OK) {number} -.type Change type, there are five different change types including:</br>
     *  0 - Initially added</br>
     *  1 - Content modified</br>
     *  2 - Version rollback</br>
     *  3 - Deleted</br>
     *  4 - Undeleted
     * @apiSuccess (200 OK) {number} -.size Content size in bytes
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "version": 1,
     *      "changed": ""
     *      "user": {
     *          "id": "544627ed3c58891f058b4611",
     *          "name": "peter.meier"
     *      },
     *      "type": 0,
     *      "size": 178,
     *  }
     * ]
     *
     * @param string $id
     * @param string $p
     */
    public function getHistory(RoleAttributeDecorator $role_decorator, ?string $id = null, ?string $p = null): Response
    {
        $result = $this->_getNode($id, $p)->getHistory();
        $body = [];
        foreach ($result as $version) {
            if ($version['user'] === null) {
                $user = null;
            } else {
                $user = $this->server->getUserById($version['user']);
                $user = $role_decorator->decorate($user, ['id', 'name', '_links']);
            }

            $body[] = [
                'version' => $version['version'],
                'changed' => $version['changed']->toDateTime()->format('c'),
                'type' => $version['type'],
                'size' => $version['size'],
                'user' => $user,
            ];
        }

        return (new Response())->setCode(200)->setBody(['data' => $body]);
    }

    /**
     * @api {post} /api/v2/files/:id/restore Rollback version
     * @apiVersion 2.0.0
     * @apiName postRestore
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Rollback to a recent version from history. Use the version number from history.
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v2/files/restore?id=544627ed3c58891f058b4686&pretty&vesion=11"
     * curl -XPOST "https://SERVER/api/v2/files/544627ed3c58891f058b4686/restore?pretty&version=1"
     * curl -XPOST "https://SERVER/api/v2/files/restore?p=/absolute/path/to/my/file&pretty&version=3"
     *
     * @apiParam (GET Parameter) {number} version The version from history to rollback to
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b4686"
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $version
     */
    public function postRestore(int $version, ?string $id = null, ?string $p = null): Response
    {
        $node = $this->_getNode($id, $p);
        $node->restore($version);
        $result = $this->node_decorator->decorate($node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {put} /api/v2/files/chunk Upload file chunk
     * @apiVersion 2.0.0
     * @apiName putChunk
     * @apiGroup Node\File
     * @apiPermission none
     * @apiUse _getNode
     * @apuUse _conflictNode
     * @apiUse _writeAction
     * @apiDescription Upload a file chunk. Use this method if you have possible big files!
     * You have to manually splitt the binary data into
     * multiple chunks and upload them successively using this method. Once uploading the last chunk,
     * the server will automatically create or update the file node.
     * You may set the parent collection, name and or custom attributes only with the last request to save traffic.
     *
     * @apiExample (cURL) example:
     * # Upload a new file myfile.jpg into the collection 544627ed3c58891f058b4686.
     * 1. First splitt the file into multiple 8M (For example, you could also use a smaller or bigger size) chunks
     * 2. Create a unique name for the session (Could also be the filename), best thing is to create a UUIDv4
     * 3. Upload each chunk successively (follow the binary order of your file!) using the chunk PUT method
     *   (The server identifies each chunk with the index parameter, beginning with #1).
     * 4. If chunk number 3 will be reached, the server automatically place all chunks to the new file node
     *
     * curl -XPUT "https://SERVER/api/v2/files/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=1&chunks=3&session=myuniquesession&size=12342442&pretty" --data-binary @chunk1.bin
     * curl -XPUT "https://SERVER/api/v2/files/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=2&chunks=3&session=myuniquesession&size=12342442&pretty" --data-binary @chunk2.bin
     * curl -XPUT "https://SERVER/api/v2/files/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=3&chunks=3&session=myuniquesession&size=12342442&pretty" --data-binary @chunk3.bin
     *
     * @apiParam (GET Parameter) {string} [id] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [p] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [collection] Either id, p (path) of a file node or a parent collection id must be given
     * (If none of them are given, the file will be placed to the root)
     * @apiParam (GET Parameter) {string} [name] Needs to be set if the chunk belongs to a new file
     * @apiParam (GET Parameter) {number} index Chunk ID
     * @apiParam (GET Parameter) {number} chunks Total number of chunks
     * @apiParam (GET Parameter) {string} session Session ID you have received during uploading the first chunk
     * @apiParam (GET Parameter) {number} size The total file size in bytes
     * @apiParam (GET Parameter) {string} [created] Set specific created ISO806 timestamp
     * @apiParam (GET Parameter) {string} [changed] Set specific changed ISO806 timestamp
     * @apiParam (GET Parameter) {bool} [readonly] Mark node readonly
     * @apiParam (GET Parameter) {object} [meta] Meta attributes
     *
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {number} data Increased version number if the last chunk was uploaded and existing node was updated.
     * It will return the old version if the submited file content was equal to the existing one.
     *
     * @apiSuccess (201 Created) {number} status Status Code
     * @apiSuccess (201 Created) {string} data Node ID if the last chunk was uploaded and a new node was added
     *
     * @apiSuccess (206 Partial Content) {number} status Status Code
     * @apiSuccess (206 Partial Content) {string} data Chunk ID if it was not the last chunk
     * @apiSuccessExample {json} Success-Response (Not the last chunk yet):
     * HTTP/1.1 206 Partial Content
     * {
     *      "session": "78297329329389e332234341",
     *      "size": 12323244224,
     *      "chunks_left": 4
     * }
     *
     * @apiSuccessExample {json} Success-Response (New file created, Last chunk):
     * HTTP/1.1 201 Created
     * {
     *      "id": "78297329329389e332234342",
     *      "version": 1
     * }
     *
     * @apiSuccessExample {json} Success-Response (File updated, Last chunk):
     * HTTP/1.1 200 OK
     * {
     *      "id": "78297329329389e332234342",
     *      "version": 2
     * }
     *
     * @apiErrorExample {json} Error-Response (quota full):
     * HTTP/1.1 507 Insufficient Storage
     * {
     *      "status": 507
     *      "data": {
     *          "error": "Balloon\Exception\InsufficientStorage",
     *          "message": "user quota is full",
     *          "code": 66
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Size limit exceeded):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "file size exceeded limit",
     *          "code": 17
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Chunks lost):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "chunks lost, reupload all chunks",
     *          "code": 275
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Chunks invalid size):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "merged chunks temp file size is not as expected",
     *          "code": 276
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $collection
     * @param string $name
     * @param string $session
     * @param string $changed
     * @param string $created
     * @param bool   $readonly
     * @param array  $meta
     *
     * @return Response
     */
    public function putChunk(
        ?ObjectId $session = null,
        ?string $id = null,
        ?string $p = null,
        ?string $collection = null,
        ?string $name = null,
        int $index = 1,
        int $chunks = 0,
        int $size = 0,
        int $conflict = 0,
        ?string $changed = null,
        ?string $created = null,
        ?bool $readonly = null,
        ?array $meta = null,
        ?array $acl = null
    ) {
        ini_set('auto_detect_line_endings', '1');
        $input = fopen('php://input', 'rb');
        if ($index > $chunks) {
            throw new Exception\InvalidArgument('chunk index can not be greater than the total number of chunks');
        }

        if ($id !== null || $p !== null) {
            $storage = $this->_getNode($id, $p)->getParent()->getStorage();
        } elseif ($id === null && $p === null && $collection === null) {
            $storage = $this->server->getFilesystem()->getRoot()->getStorage();
        } else {
            $storage = $this->_getNode($collection, null, Collection::class)->getStorage();
        }

        if ($session === null) {
            $session = $storage->storeTemporaryFile($input, $this->server->getIdentity());
        } else {
            $storage->storeTemporaryFile($input, $this->server->getIdentity(), $session);
        }

        if ($index === $chunks) {
            $attributes = compact('changed', 'created', 'readonly', 'meta', 'acl');
            $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });
            $attributes = $this->_verifyAttributes($attributes);

            return $this->_put($session, $id, $p, $collection, $name, $attributes, $conflict);
        }

        return (new Response())->setCode(206)->setBody([
                'session' => (string) $session,
                'chunks_left' => $chunks - $index,
            ]);
    }

    /**
     * @api {put} /api/v2/files Upload file
     * @apiVersion 2.0.0
     * @apiName put
     * @apiGroup Node\File
     * @apiPermission none
     * @apiUse _getNode
     * @apiUse _conflictNode
     * @apiUse _writeAction
     *
     * @apiDescription Upload an entire file in one-shot. Attention, there is file size limit,
     * if you have possible big files use the method PUT chunk!
     *
     * @apiExample (cURL) example:
     * #Update content of file 544627ed3c58891f058b4686
     * curl -XPUT "https://SERVER/api/v2/files?id=544627ed3c58891f058b4686" --data-binary myfile.txt
     * curl -XPUT "https://SERVER/api/v2/files/544627ed3c58891f058b4686" --data-binary myfile.txt
     *
     * #Upload new file under collection 544627ed3c58891f058b3333
     * curl -XPUT "https://SERVER/api/v2/files?collection=544627ed3c58891f058b3333&name=myfile.txt" --data-binary myfile.txt
     *
     * @apiParam (GET Parameter) {string} [id] Either id, p (path) of a file node or a parent collection id must be given
     *
     * @apiParam (GET Parameter) {string} [id] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [p] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [collection] Either id, p (path) of a file node or a parent collection id must be given
     * (If none of them are given, the file will be placed to the root)
     * @apiParam (GET Parameter) {string} [name] Needs to be set if the chunk belongs to a new file
     * or to identify an existing child file if a collection id was set
     * @apiParam (GET Parameter) {string} [created] Set specific created ISO806 timestamp
     * @apiParam (GET Parameter) {string} [changed] Set specific changed ISO806 timestamp
     * @apiParam (GET Parameter) {bool} [readonly] Mark node readonly
     * @apiParam (GET Parameter) {object} [meta] Meta attributes
     *
     * @apiSuccess (200 OK) {number} status Status Code
     * @apiSuccess (200 OK) {number} data Increased version number if an existing file was updated. It will return
     * the old version if the submited file content was equal to the existing one.
     *
     * @apiSuccess (201 Created) {number} status Status Code
     * @apiSuccess (201 Created) {string} data Node ID
     * @apiSuccessExample {json} Success-Response (New file created):
     * HTTP/1.1 201 Created
     * {
     *      "status": 201,
     *      "data": "78297329329389e332234342"
     * }
     *
     * @apiSuccessExample {json} Success-Response (File updated):
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": 2
     * }
     *
     * @apiErrorExample {json} Error-Response (quota full):
     * HTTP/1.1 507 Insufficient Storage
     * {
     *      "status": 507
     *      "data": {
     *          "error": "Balloon\Exception\InsufficientStorage",
     *          "message": "user quota is full",
     *          "code": 65
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Size limit exceeded):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "file size exceeded limit",
     *          "code": 17
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $collection
     * @param string $name
     * @param string $changed
     * @param string $created
     * @param bool   $readonly
     * @param array  $meta
     */
    public function put(
        ?string $id = null,
        ?string $p = null,
        ?string $collection = null,
        ?string $name = null,
        int $conflict = 0,
        ?string $changed = null,
        ?string $created = null,
        ?bool $readonly = null,
        ?array $meta = null,
        ?array $acl = null
    ): Response {
        ini_set('auto_detect_line_endings', '1');
        $input = fopen('php://input', 'rb');

        if ($id !== null || $p !== null) {
            $storage = $this->_getNode($id, $p)->getParent()->getStorage();
        } elseif ($id === null && $p === null && $collection === null) {
            $storage = $this->server->getFilesystem()->getRoot()->getStorage();
        } else {
            $storage = $this->_getNode($collection, null, Collection::class)->getStorage();
        }

        $session = $storage->storeTemporaryFile($input, $this->server->getIdentity());
        $attributes = compact('changed', 'created', 'readonly', 'meta', 'acl');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });
        $attributes = $this->_verifyAttributes($attributes);

        return $this->_put($session, $id, $p, $collection, $name, $attributes, $conflict);
    }

    /**
     * Add or update file.
     *
     * @param string $id
     * @param string $p
     * @param string $collection
     * @param string $name
     */
    protected function _put(
        ObjectId $session,
        ?string $id = null,
        ?string $p = null,
        ?string $collection = null,
        ?string $name = null,
        array $attributes = [],
        int $conflict = 0
    ): Response {
        if (null === $id && null === $p && null === $name) {
            throw new Exception\InvalidArgument('neither id, p nor name was set');
        }

        if (null !== $p && null !== $name) {
            throw new Exception\InvalidArgument('p and name can not be used at the same time');
        }

        try {
            if (null !== $p) {
                $node = $this->_getNode(null, $p);
                $node->setContent($session, $attributes);
                $result = $this->node_decorator->decorate($node);

                return (new Response())->setCode(200)->setBody($result);
            }
            if (null !== $id && null === $collection) {
                $node = $this->_getNode($id);
                $node->setContent($session, $attributes);
                $result = $this->node_decorator->decorate($node);

                return (new Response())->setCode(200)->setBody($result);
            }
            if (null === $p && null === $id && null !== $name) {
                $collection = $this->_getNode($collection, null, Collection::class, false, true);

                if ($collection->childExists($name)) {
                    $child = $collection->getChild($name);
                    $child->setContent($session, $attributes);
                    $result = $this->node_decorator->decorate($child);

                    return (new Response())->setCode(200)->setBody($result);
                }
                if (!is_string($name) || empty($name)) {
                    throw new Exception\InvalidArgument('name must be a valid string');
                }

                $result = $collection->addFile($name, $session, $attributes);
                $result = $this->node_decorator->decorate($result);

                return (new Response())->setCode(201)->setBody($result);
            }
        } catch (ForbiddenException $e) {
            throw new Exception\Conflict(
                'a node called '.$name.' does already exists in this collection',
                Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS,
                $e
            );
        } catch (Exception\NotFound $e) {
            if (null !== $p && null === $id) {
                if (!is_string($p) || empty($p)) {
                    throw new Exception\InvalidArgument('path (p) must be a valid string');
                }

                $parent_path = dirname($p);
                $name = basename($p);

                try {
                    $parent = $this->fs->findNodeByPath($parent_path, Collection::class);

                    if (!is_string($name) || empty($name)) {
                        throw new Exception\InvalidArgument('name must be a valid string');
                    }

                    $result = $parent->addFile($name, $session, $attributes);
                    $result = $this->node_decorator->decorate($result);

                    return (new Response())->setCode(201)->setBody($result);
                } catch (Exception\NotFound $e) {
                    throw new Exception('parent collection '.$parent_path.' was not found');
                }
            } else {
                throw $e;
            }
        }
    }
}
