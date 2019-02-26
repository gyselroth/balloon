<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection as NodeCollection;
use Balloon\Helper;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Micro\Http\Response;
use function MongoDB\BSON\fromJSON;
use function MongoDB\BSON\toPHP;

class Collections extends Nodes
{
    /**
     * Get children.
     *
     * @param null|mixed $query
     */
    public function getChildren(
        ?string $id = null,
        ?string $p = null,
        int $deleted = 0,
        $query = null,
        array $attributes = [],
        ?int $offset = 0,
        ?int $limit = 20,
        ?bool $recursive = false
    ): Response {
        $children = [];

        $node = $this->fs->getNode($id, $p, null, false, true);
        if ($node->isRoot()) {
            $uri = '/api/v2/collections/children';
        } else {
            $uri = '/api/v2/collections/'.$node->getId().'/children';
        }

        if ($query === null) {
            $query = [];
        } elseif (is_string($query)) {
            $query = toPHP(fromJSON($query), [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);
        }

        $nodes = $this->fs->getNode($id, $p, NodeCollection::class, false, true)->getChildNodes($deleted, $query, $offset, $limit, $recursive);
        $pager = new Pager($this->node_decorator, $nodes, $attributes, $offset, $limit, $uri);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get Share ACL.
     */
    public function getShare(RoleAttributeDecorator $role_decorator, ?string $id = null, ?string $p = null, array $attributes = []): Response
    {
        $node = $this->fs->getNode($id, $p);

        if (!$node->isShared()) {
            throw new Exception\Conflict('node is not a share', Exception\Conflict::NOT_SHARED);
        }

        $acl = $node->getAcl();

        foreach ($acl as &$rule) {
            $rule['role'] = $role_decorator->decorate($rule['role'], $attributes);
        }

        return (new Response())->setCode(200)->setBody([
            'name' => $node->getShareName(),
            'acl' => $acl,
        ]);
    }

    /**
     * Create share.
     */
    public function postShare(array $acl, string $name, ?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $node->share($acl, $name);
        $result = $this->node_decorator->decorate($node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Delete share.
     */
    public function deleteShare(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p);
        $result = $node->unshare();

        return (new Response())->setCode(204);
    }

    /**
     * Create collection.
     */
    public function post(
        ?string $id = null,
        ?string $p = null,
        ?string $name = null,
        array $attributes = [],
        int $conflict = 0
    ): Response {
        if (null !== $p && null !== $name) {
            throw new Exception\InvalidArgument('p and name can not be used at the same time');
        }

        $attributes = $this->_verifyAttributes($attributes);

        if (null === $id && null !== $p) {
            if (!is_string($p) || empty($p)) {
                throw new Exception\InvalidArgument('name must be a valid string');
            }

            $parent_path = dirname($p);
            $name = Helper::mb_basename($p);
            $parent = $this->fs->findNodeByPath($parent_path, NodeCollection::class);
            $result = $parent->addDirectory($name, $attributes, $conflict);
            $result = $this->node_decorator->decorate($result);

            return (new Response())->setCode(201)->setBody($result);
        }

        if (null !== $id && null === $name) {
            throw new Exception\InvalidArgument('name must be set with id');
        }

        $parent = $this->fs->getNode($id, null, null, false, true);
        $result = $parent->addDirectory((string) $name, $attributes, $conflict);
        $result = $this->node_decorator->decorate($result);

        return (new Response())->setCode(201)->setBody($result);
    }
}
