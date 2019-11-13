<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

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
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Helper;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Http\Response;
use function MongoDB\BSON\fromJSON;
use function MongoDB\BSON\toPHP;
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
     * Restore node.
     */
    public function postUndelete(
        $id,
        bool $move = false,
        ?string $destid = null,
        int $conflict = 0
    ): Response {
        $parent = null;
        if (true === $move) {
            try {
                $parent = $this->fs->getNode($destid, Collection::class, false, true);
            } catch (Exception\NotFound $e) {
                throw new Exception\NotFound('destination collection was not found or is not a collection', Exception\NotFound::DESTINATION_NOT_FOUND);
            }
        }

        return $this->bulk($id, function ($node) use ($parent, $conflict, $move) {
            if (true === $move) {
                $node = $node->setParent($parent, $conflict);
            }

            $node->undelete($conflict);

            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node),
            ];
        });
    }

    /**
     * Download stream.
     *
     * @param null|mixed $id
     */
    public function getContent(
        $id = null,
        bool $download = false,
        string $name = 'selected',
        ?string $encoding = null
    ): ?Response {
        if (is_array($id)) {
            return $this->combine($id, $name);
        }

        $node = $this->_getNode($id);
        if ($node instanceof Collection) {
            return $node->getZip();
        }

        $response = new Response();

        return ApiHelper::streamContent($response, $node, $download);
    }

    /**
     * Get attributes.
     *
     * @param null|mixed $id
     * @param null|mixed $query
     */
    public function get($id = null, int $deleted = 0, $query = null, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if ($id === null) {
            $query = $this->parseQuery($query);

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

        return $this->bulk($id, function ($node) use ($attributes) {
            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node, $attributes),
            ];
        });
    }

    /**
     * Get parent nodes.
     */
    public function getParents(string $id, array $attributes = [], bool $self = false): Response
    {
        $result = [];
        $request = $this->_getNode($id);
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
     * Change attributes.
     *
     * @param null|mixed $lock
     */
    public function patch(string $id, ?string $name = null, ?array $meta = null, ?bool $readonly = null, ?array $filter = null, $lock = null): Response
    {
        $attributes = compact('name', 'meta', 'readonly', 'filter', 'lock');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $lock = $_SERVER['HTTP_LOCK_TOKEN'] ?? null;

        return $this->bulk($id, function ($node) use ($attributes, $lock) {
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
                    case 'lock':
                        if ($value === false) {
                            $node->unlock($lock);
                        } else {
                            $node->lock($lock);
                        }

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
     * Clone node.
     */
    public function postClone(
        $id,
        ?string $destid = null,
        int $conflict = 0
    ): Response {
        try {
            $parent = $this->fs->getNode($destid, Collection::class, false, true);
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound('destination collection was not found or is not a collection', Exception\NotFound::DESTINATION_NOT_FOUND);
        }

        return $this->bulk($id, function ($node) use ($parent, $conflict) {
            $result = $node->copyTo($parent, $conflict);

            return [
                'code' => $parent == $result ? 200 : 201,
                'data' => $this->node_decorator->decorate($result),
            ];
        });
    }

    /**
     * Move node.
     */
    public function postMove(
        $id,
        ?string $destid = null,
        int $conflict = 0
    ): Response {
        try {
            $parent = $this->fs->getNode($destid, Collection::class, false, true);
        } catch (Exception\NotFound $e) {
            throw new Exception\NotFound('destination collection was not found or is not a collection', Exception\NotFound::DESTINATION_NOT_FOUND);
        }

        return $this->bulk($id, function ($node) use ($parent, $conflict) {
            $result = $node->setParent($parent, $conflict);

            return [
                'code' => 200,
                'data' => $this->node_decorator->decorate($node),
            ];
        });
    }

    /**
     * Delete node.
     */
    public function delete(
        $id,
        bool $force = false,
        bool $ignore_flag = false,
        ?string $at = null
    ): Response {
        $failures = [];

        if (null !== $at && '0' !== $at) {
            $at = $this->_verifyAttributes(['destroy' => $at])['destroy'];
        }

        return $this->bulk($id, function ($node) use ($force, $ignore_flag, $at) {
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
     * Get trash.
     *
     * @param null|mixed $query
     */
    public function getTrash($query = null, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        $query = $this->parseQuery($query);
        $nodes = $this->fs->getTrash($query, $offset, $limit);

        if ($this instanceof ApiFile) {
            $query['directory'] = false;
            $uri = '/api/v2/files';
        } elseif ($this instanceof ApiCollection) {
            $query['directory'] = true;
            $uri = '/api/v2/collections';
        } else {
            $uri = '/api/v2/nodes';
        }

        $pager = new Pager($this->node_decorator, $nodes, $attributes, $offset, $limit, $uri);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get delta.
     */
    public function getDelta(
        DeltaAttributeDecorator $delta_decorator,
        ?string $id = null,
        ?string $cursor = null,
        int $limit = 250,
        array $attributes = []
    ): Response {
        if (null !== $id) {
            $node = $this->_getNode($id);
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
     * Event log.
     *
     * @param null|mixed $query
     */
    public function getEventLog(EventAttributeDecorator $event_decorator, ?string $id = null, $query = null, ?string $sort = null, ?array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if (null !== $id) {
            $node = $this->_getNode($id);
            $uri = '/api/v2/nodes/'.$node->getId().'/event-log';
        } else {
            $node = null;
            $uri = '/api/v2/nodes/event-log';
        }

        $query = $this->parseQuery($query);
        $result = $this->fs->getDelta()->getEventLog($query, $limit, $offset, $node, $total);
        $pager = new Pager($event_decorator, $result, $attributes, $offset, $limit, $uri, $total);

        return (new Response())->setCode(200)->setBody($pager->paging());
    }

    /**
     * Get last Cursor.
     */
    public function getLastCursor(?string $id = null): Response
    {
        if (null !== $id) {
            $node = $this->_getNode($id);
        } else {
            $node = null;
        }

        $result = $this->fs->getDelta()->getLastCursor();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Parse query.
     */
    protected function parseQuery($query): array
    {
        if ($query === null) {
            $query = [];
        } elseif (is_string($query)) {
            $query = toPHP(fromJSON($query), [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);
        }

        return (array) $query;
    }

    /**
     * Merge multiple nodes into one zip archive.
     *
     * @param null|mixed $id
     */
    protected function combine($id = null, string $name = 'selected')
    {
        $archive = new ZipStream($name.'.zip');

        foreach ($this->_getNodes($id) as $node) {
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
            'lock',
        ];

        if ($this instanceof ApiCollection) {
            $valid_attributes += ['filter', 'mount'];
        }

        $check = array_merge(array_flip($valid_attributes), $attributes);

        if ($this instanceof ApiCollection && count($check) > 9) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, lock, filter, mount, readonly and/or meta attributes may be overwritten');
        }
        if ($this instanceof ApiFile && count($check) > 7) {
            throw new Exception\InvalidArgument('Only changed, created, destroy timestamp, lock, readonly and/or meta attributes may be overwritten');
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
