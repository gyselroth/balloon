<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Files extends Nodes
{
    /**
     * Get history.
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
     * Rollback version.
     */
    public function postRestore(int $version, ?string $id = null, ?string $p = null): Response
    {
        $node = $this->_getNode($id, $p);
        $node->restore($version);
        $result = $this->node_decorator->decorate($node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Upload file chunk.
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

        $storage = $this->getStorage($id, $p, $collection);

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
     * Upload file.
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

        $storage = $this->getStorage($id, $p, $collection);
        $session = $storage->storeTemporaryFile($input, $this->server->getIdentity());
        $attributes = compact('changed', 'created', 'readonly', 'meta', 'acl');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });
        $attributes = $this->_verifyAttributes($attributes);

        return $this->_put($session, $id, $p, $collection, $name, $attributes, $conflict);
    }

    /**
     * Get storage.
     */
    protected function getStorage($id, $p, $collection): StorageAdapterInterface
    {
        if ($id !== null) {
            return $this->_getNode($id, $p)->getParent()->getStorage();
        }
        if ($p !== null) {
            $path = '/'.ltrim(dirname('/'.$p), '/');

            return $this->_getNode($id, $path, Collection::class)->getStorage();
        }
        if ($id === null && $p === null && $collection === null) {
            return $this->server->getFilesystem()->getRoot()->getStorage();
        }

        return $this->_getNode($collection, null, Collection::class)->getStorage();
    }

    /**
     * Add or update file.
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

                if ($collection->childExists($name, NodeInterface::DELETED_INCLUDE, ['directory' => false])) {
                    $child = $collection->getChild($name, NodeInterface::DELETED_INCLUDE, ['directory' => false]);
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

                $parent_path = '/'.ltrim(dirname('/'.$p), '/');
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
