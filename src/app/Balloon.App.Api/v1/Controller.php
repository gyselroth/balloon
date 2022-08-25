<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Balloon\App\Api\v2\Collections as ApiCollection;
use Balloon\App\Api\v2\Files as ApiFile;
use Balloon\Filesystem;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Closure;
use Generator;
use Micro\Http\ExceptionInterface;
use Micro\Http\Response;

abstract class Controller
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

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
     * Load node with path.
     */
    public function findNodeByPath(string $path = '', ?string $class = null): NodeInterface
    {
        if (empty($path) || '/' !== $path[0]) {
            $path = '/'.$path;
        }

        $last = strlen($path) - 1;
        if ('/' === $path[$last]) {
            $path = substr($path, 0, -1);
        }

        $parts = explode('/', $path);
        $parent = $this->fs->getRoot();
        array_shift($parts);
        $count = count($parts);

        $i = 0;
        $filter = [];

        foreach ($parts as $node) {
            ++$i;

            if ($count === $i && $class !== null) {
                $filter = [
                    'directory' => ($class === Collection::class),
                ];
            }

            try {
                $parent = $parent->getChild($node, NodeInterface::DELETED_EXCLUDE, $filter);
            } catch (Exception\NotFound $e) {
                if ($count == $i) {
                    $parent = $parent->getChild($node, NodeInterface::DELETED_INCLUDE, $filter);
                } else {
                    throw $e;
                }
            }
        }

        if (null !== $class && !($parent instanceof $class)) {
            throw new Exception('node is not instance of '.$class);
        }

        return $parent;
    }

    /**
     * Load nodes by id.
     */
    public function findNodesByPath(array $path = [], ?string $class = null): Generator
    {
        $find = [];
        foreach ($path as $p) {
            if (empty($path) || '/' !== $path[0]) {
                $path = '/'.$path;
            }

            $last = strlen($path) - 1;
            if ('/' === $path[$last]) {
                $path = substr($path, 0, -1);
            }

            $parts = explode('/', $path);
            $parent = $this->fs->getRoot();
            array_shift($parts);
            foreach ($parts as $node) {
                $parent = $parent->getChild($node, NodeInterface::DELETED_EXCLUDE);
            }

            if (null !== $class && !($parent instanceof $class)) {
                throw new Exception('node is not an instance of '.$class);
            }

            yield $parent;
        }
    }

    /**
     * Load nodes by id.
     *
     * @param null|mixed $class
     */
    public function getNodes(?array $id = null, ?array $path = null, $class = null, int $deleted = NodeInterface::DELETED_EXCLUDE): Generator
    {
        if (null === $id && null === $path) {
            throw new Exception\InvalidArgument('neither parameter id nor p (path) was given');
        }
        if (null !== $id && null !== $path) {
            throw new Exception\InvalidArgument('parameter id and p (path) can not be used at the same time');
        }
        if (null !== $id) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_INCLUDE;
            }

            return $this->fs->findNodesById($id, $class, $deleted);
        }
        if (null !== $path) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_EXCLUDE;
            }

            return $this->findNodesByPath($path, $class);
        }
    }

    /**
     * Load node.
     *
     * @param null|mixed $id
     * @param null|mixed $path
     * @param null|mixed $class
     */
    public function getNode($id = null, $path = null, $class = null, bool $multiple = false, bool $allow_root = false, ?int $deleted = null): NodeInterface
    {
        if (empty($id) && empty($path)) {
            if (true === $allow_root) {
                return $this->fs->getRoot();
            }

            throw new Exception\InvalidArgument('neither parameter id nor p (path) was given');
        }
        if (null !== $id && null !== $path) {
            throw new Exception\InvalidArgument('parameter id and p (path) can not be used at the same time');
        }
        if (null !== $id) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_INCLUDE;
            }

            return $this->fs->findNodeById($id, $class, $deleted);
        }

        return $this->findNodeByPath($path, $class);
    }

    /**
     * Do bulk operations.
     *
     * @param array|string $id
     * @param array|string $p
     */
    protected function bulk($id, $p, Closure $action): Response
    {
        if (is_array($id) || is_array($p)) {
            $body = [];
            foreach ($this->_getNodes($id, $p) as $node) {
                try {
                    $body[(string) $node->getId()] = $action->call($this, $node);
                } catch (\Exception $e) {
                    $body[(string) $node->getId()] = [
                        'code' => $e instanceof ExceptionInterface ? $e->getStatusCode() : 400,
                        'data' => [
                            'error' => get_class($e),
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                        ],
                    ];
                }
            }

            return (new Response())->setCode(207)->setBody($body);
        }

        $body = $action->call($this, $this->_getNode($id, $p));
        $response = (new Response())->setCode($body['code']);

        if (isset($body['data'])) {
            $response->setBody($body['data']);
        }

        return $response;
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
            switch (static::class) {
                case ApiFile::class:
                    $class = File::class;

                break;
                case ApiCollection::class:
                    $class = Collection::class;

                break;
            }
        }

        return $this->fs->getNode($id, $class, $multiple, $allow_root, $deleted);
    }

    /**
     * Get nodes.
     *
     * @param array|string $id
     * @param string       $path
     * @param string       $class   Force set node type
     * @param bool         $deleted How to handle deleted node
     */
    protected function _getNodes(
        $id = null,
        $path = null,
        ?string $class = null,
        int $deleted = 2
    ): Generator {
        if (null === $class) {
            switch (static::class) {
                case ApiFile::class:
                    $class = File::class;

                break;
                case ApiCollection::class:
                    $class = Collection::class;

                break;
            }
        }

        return $this->fs->getNodes($id, $class, $deleted);
    }
}
