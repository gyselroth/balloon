<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @category    Balloon
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2016 gyselroth GmbH
 */

namespace Balloon\Api\v1;

use \Balloon\Exception;
use \Balloon\Helper;
use \Micro\Http\Response;
use \Balloon\Filesystem\Node\Collection;
use \Balloon\Converter;

class File extends Node
{
    /**
     * @api {get} /api/v1/file/history?id=:id Get history
     * @apiVersion 1
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
     * @param  string $id
     * @param  string $p
     * @return Response
     */
    public function getHistory(?string $id=null, ?string $p=null): Response
    {
        $result = Helper::escape(
            $this->_getNode($id, $p)->getHistory()
        );
        return (new Response())->setCode(200)->setBody($result);
    }


    /**
     * @api {post} /api/v1/file/restore?id=:id Rollback version
     * @apiVersion 1
     * @apiName postRestore
     * @apiGroup Node\File
     * @apiPermission none
     * @apiDescription Rollback to a recent version from history. Use the version number from history.
     * @apiUse _getNode
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/file/restore?id=544627ed3c58891f058b4686&pretty&vesion=11"
     * curl -XPOST "https://SERVER/api/v1/file/544627ed3c58891f058b4686/restore?pretty&version=1"
     * curl -XPOST "https://SERVER/api/v1/file/restore?p=/absolute/path/to/my/file&pretty&version=3"
     *
     * @apiParam (GET Parameter) {number} version The version from history to rollback to
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param   string $id
     * @param   string $p
     * @param   string $version
     * @return  Response
     */
    public function postRestore(int $version, ?string $id=null, ?string $p=null): Response
    {
        $result = $this->_getNode($id, $p)->restore($version);
        return (new Response())->setCode(204);
    }


    /**
     * @api {put} /api/v1/file/chunk Upload file chunk
     * @apiVersion 1
     * @apiName putChunk
     * @apiGroup Node\File
     * @apiPermission none
     * @apiUse _getNode
     * @apiUse _conflictNode
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
     * 2. Create a unique name for the chunkgroup (Could also be the filename), best thing is to create a UUIDv4
     * 3. Upload each chunk successively (follow the binary order of your file!) using the chunk PUT method
     *   (The server identifies each chunk with the index parameter, beginning with #1).
     * 4. If chunk number 3 will be reached, the server automatically place all chunks to the new file node
     *
     * curl -XPUT "https://SERVER/api/v1/file/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=1&chunks=3&chunkgroup=myuniquechunkgroup&size=12342442&pretty" --data-binary @chunk1.bin
     * curl -XPUT "https://SERVER/api/v1/file/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=2&chunks=3&chunkgroup=myuniquechunkgroup&size=12342442&pretty" --data-binary @chunk2.bin
     * curl -XPUT "https://SERVER/api/v1/file/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=3&chunks=3&chunkgroup=myuniquechunkgroup&size=12342442&pretty" --data-binary @chunk3.bin
     *
     * @apiParam (GET Parameter) {string} [id] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [p] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [collection] Either id, p (path) of a file node or a parent collection id must be given
     * (If none of them are given, the file will be placed to the root)
     * @apiParam (GET Parameter) {string} [name] Needs to be set if the chunk belongs to a new file
     * @apiParam (GET Parameter) {number} index Chunk ID (consider chunk order!)
     * @apiParam (GET Parameter) {number} chunks Total number of chunks
     * @apiParam (GET Parameter) {string} chunkgroup A unique name which identifes a group of chunks (One file)
     * @apiParam (GET Parameter) {number} size The total file size in bytes
     * @apiParam (GET Parameter) {object} [attributes] Overwrite some attributes which are usually generated on the server
     * @apiParam (GET Parameter) {number} [attributes.created] Set specific created timestamp (UNIX timestamp format)
     * @apiParam (GET Parameter) {number} [attributes.changed] Set specific changed timestamp (UNIX timestamp format)
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
     *      "status": 206,
     *      "data": "1"
     * }
     *
     * @apiSuccessExample {json} Success-Response (New file created, Last chunk):
     * HTTP/1.1 201 Created
     * {
     *      "status": 201,
     *      "data": "78297329329389e332234342"
     * }
     *
     * @apiSuccessExample {json} Success-Response (File updated, Last chunk):
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
     * @param  string $id
     * @param  string $p
     * @param  string $collection
     * @param  string $name
     * @param  int $index
     * @param  int $chunks
     * @param  string $chunkgroup
     * @param  int $size
     * @param  array $attributes
     * @param  int $conflict
     * @return Response
     */
    public function putChunk(
        string $chunkgroup,
        ?string $id=null,
        ?string $p=null,
        ?string $collection=null,
        ?string $name=null,
        int $index=1,
        int $chunks=0,
        int $size=0,
        array $attributes=[],
        int $conflict=0
    ) {
        ini_set('auto_detect_line_endings', '1');
        $input_handler = fopen('php://input', 'rb');
        if (!is_string($chunkgroup) || empty($chunkgroup)) {
            throw new Exception\InvalidArgument('chunkgroup must be valid unique string');
        }

        if ($index > $chunks) {
            throw new Exception\InvalidArgument('chunk index can not be greater than the total number of chunks');
        }

        $chunkgroup = Helper::filter($chunkgroup);
        $folder     = $this->server->getTempDir().DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.$this->user->getId();

        if (!file_exists($folder)) {
            mkdir($folder, 0700, true);
        }

        $file = $folder.DIRECTORY_SEPARATOR.$chunkgroup;

        $tmp_size = 0;
        if (file_exists($file)) {
            $tmp_size = filesize($file);
        } elseif ($index > 1) {
            throw new Exception\Conflict(
                'chunks lost, reupload all chunks',
                Exception\Conflict::CHUNKS_LOST
            );
        }

        $chunkgroup_handler = fopen($file, 'a+');
        while (!feof($input_handler)) {
            $data  = fread($input_handler, 1024);
            $wrote = fwrite($chunkgroup_handler, $data);
            $tmp_size += $wrote;

            if ($tmp_size > (int)$this->server->getMaxFileSize()) {
                fclose($input_handler);
                fclose($chunkgroup_handler);
                unlink($file);
                throw new Exception\InsufficientStorage(
                    'file size exceeded limit',
                    Exception\InsufficientStorage::FILE_SIZE_LIMIT
                );
            }
        }

        if ($index == $chunks) {
            clearstatcache();
            if (!is_readable($file)) {
                throw new Exception\Conflict(
                    'chunks lost, reupload all chunks',
                    Exception\Conflict::CHUNKS_LOST
                );
            }

            if ($tmp_size != $size) {
                fclose($chunkgroup_handler);
                unlink($file);
                throw new Exception\Conflict(
                    'merged chunks temp file size is not as expected',
                    Exception\Conflict::CHUNKS_INVALID_SIZE
                );
            }

            try {
                $attributes = $this->_verifyAttributes($attributes);
                return $this->_put($file, $id, $p, $collection, $name, $attributes, $conflict);
            } catch (\Exception $e) {
                unlink($file);
                throw $e;
            }
        } else {
            return (new Response())->setCode(206)->setBody($index);
        }
    }


    /**
     * @api {put} /api/v1/file Upload file
     * @apiVersion 1
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
     * curl -XPUT "https://SERVER/api/v1/file?id=544627ed3c58891f058b4686" --data-binary myfile.txt
     * curl -XPUT "https://SERVER/api/v1/file/544627ed3c58891f058b4686" --data-binary myfile.txt
     *
     * #Upload new file under collection 544627ed3c58891f058b3333
     * curl -XPUT "https://SERVER/api/v1/file?collection=544627ed3c58891f058b3333&name=myfile.txt" --data-binary myfile.txt
     *
     * @apiParam (GET Parameter) {string} [id] Either id, p (path) of a file node or a parent collection id must be given
     *
     * @apiParam (GET Parameter) {string} [id] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [p] Either id, p (path) of a file node or a parent collection id must be given
     * @apiParam (GET Parameter) {string} [collection] Either id, p (path) of a file node or a parent collection id must be given
     * (If none of them are given, the file will be placed to the root)
     * @apiParam (GET Parameter) {string} [name] Needs to be set if the chunk belongs to a new file
     * or to identify an existing child file if a collection id was set
     * @apiParam (GET Parameter) {object} attributes Overwrite some attributes which are usually generated on the server
     * @apiParam (GET Parameter) {number} attributes.created Set specific created timestamp (UNIX timestamp format)
     * @apiParam (GET Parameter) {number} attributes.changed Set specific changed timestamp (UNIX timestamp format)
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
     * @param  string $id
     * @param  string $p
     * @param  string $collection
     * @param  string $name
     * @param  array $attributes
     * @param  int $conflict
     * @return Response
     */
    public function put(
        ?string $id=null,
        ?string $p=null,
        ?string $collection=null,
        ?string $name=null,
        array $attributes=[],
        int $conflict=0
    ): Response {
        $attributes = $this->_verifyAttributes($attributes);

        ini_set('auto_detect_line_endings', '1');
        $content = fopen('php://input', 'rb');
        return $this->_put($content, $id, $p, $collection, $name, $attributes, $conflict);
    }


    /**
     * Add or update file
     *
     * @param  string|resource $content
     * @param  string $id
     * @param  string $p
     * @param  string $collection
     * @param  string $name
     * @param  array $attributes
     * @param  int $conflict
     * @return Response
     */
    protected function _put(
        $content,
        ?string $id=null,
        ?string $p=null,
        ?string $collection=null,
        ?string $name=null,
        array $attributes=[],
        int $conflict=0
    ): Response {
        if ($id === null && $p === null && $name === null) {
            throw new Exception\InvalidArgument('neither id, p nor name was set');
        }

        if ($p !== null && $name !== null) {
            throw new Exception\InvalidArgument('p and name can not be used at the same time');
        }

        try {
            if ($p !== null) {
                $node = $this->_getNode(null, $p);
                $result = $node->put($content, false, $attributes);
                return (new Response())->setCode(200)->setBody($result);
            } elseif ($id !== null && $collection === null) {
                $node = $this->_getNode($id);
                $result = $node->put($content, false, $attributes);
                return (new Response())->setCode(200)->setBody($result);
            } elseif ($p === null && $id === null && $name !== null) {
                $collection =  $this->_getNode($collection, null, 'Collection', false, true);

                if ($collection->childExists($name)) {
                    $child = $collection->getChild($name);
                    $result = $child->put($content, false, $attributes);
                    return (new Response())->setCode(200)->setBody($result);
                } else {
                    if (!is_string($name) || empty($name)) {
                        throw new Exception\InvalidArgument('name must be a valid string');
                    }

                    $name = Helper::filter($name);
                    $result = $collection->addFile($name, $content, $attributes)->getId(true);
                    return (new Response())->setCode(201)->setBody($result);
                }
            }
        } catch (Exception\Forbidden $e) {
            throw new Exception\Conflict('a node called '.$name.' does already exists in this collection',
                Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS
            );
        } catch (Exception\NotFound $e) {
            if ($p !== null && $id === null) {
                if (!is_string($p) || empty($p)) {
                    throw new Exception\InvalidArgument('path (p) must be a valid string');
                }

                $p = Helper::filter($p);
                $parent_path = dirname($p);
                $name = basename($p);
                try {
                    $parent = $this->fs->findNodeWithPath($parent_path, 'Collection');

                    if (!is_string($name) || empty($name)) {
                        throw new Exception\InvalidArgument('name must be a valid string');
                    }

                    $result = $parent->addFile($name, $content, $attributes)->getId(true);
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
