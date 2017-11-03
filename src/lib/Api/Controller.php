<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api;

use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Psr\Log\LoggerInterface;
use Balloon\Api\v1\Collection as CollectionController;
use Balloon\Api\v1\File as FileController;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;

class Controller
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
     * Initialize.
     *
     * @param Filesystem      $fs
     * @param Config          $config
     * @param LoggerInterface $logger
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->fs = $server->getFilesystem();
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->logger = $logger;
    }

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
     * Get node.
     *
     * @param string $id
     * @param string $path
     * @param string $class      Force set node type
     * @param bool   $deleted
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
        switch(get_class($this)) {
            case FileController::class:
                $class = File::class;
            break;
            case CollectionController::class:
                $class = Collection::class;
            break;
            default:
                $class = null;
            break;
        }

        return $this->fs->getNode($id, $path, $class, $multiple, $allow_root, $deleted);
    }
}
