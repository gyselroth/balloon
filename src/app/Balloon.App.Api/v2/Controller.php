<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\App\Api\v2\Collections as ApiCollection;
use Balloon\App\Api\v2\Files as ApiFile;
use Balloon\Filesystem;
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
     * Do bulk operations.
     */
    protected function bulk($id, Closure $action): Response
    {
        if (is_array($id)) {
            $body = [];
            foreach ($this->_getNodes($id) as $node) {
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

        $body = $action->call($this, $this->_getNode($id));
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
     * @param string $class      Force set node type
     * @param bool   $multiple   Allow $id to be an array
     * @param bool   $allow_root Allow instance of root collection
     * @param bool   $deleted    How to handle deleted node
     */
    protected function _getNode(
        ?string $id = null,
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

        return $this->fs->getNode($id, $class, $multiple, $allow_root, $deleted);
    }

    /**
     * Get nodes.
     *
     * @param null|mixed $id
     */
    protected function _getNodes(
        $id = null,
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

        return $this->fs->getNodes($id, $class, $deleted);
    }
}
