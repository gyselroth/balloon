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
use Balloon\Filesystem\Node\Collection;
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
        int $deleted = 0,
        $query = null,
        array $attributes = [],
        ?int $offset = 0,
        ?int $limit = 20,
        ?bool $recursive = false
    ): Response {
        $children = [];
        $node = $this->fs->getNode($id, Collection::class, false, true);
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
        $this->logger->debug(json_encode($query));

        $nodes = $this->fs->getNode($id, Collection::class, false, true)->getChildNodes($deleted, $query, $offset, $limit, $recursive);
        $pager = new Pager($this->node_decorator, $nodes, $attributes, $offset, $limit, $uri);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get Share ACL.
     */
    public function getShare(RoleAttributeDecorator $role_decorator, string $id, array $attributes = []): Response
    {
        $node = $this->fs->getNode($id, Collection::class);

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
    public function postShare(array $acl, string $name, string $id): Response
    {
        $node = $this->fs->getNode($id, Collection::class);
        $node->share($acl, $name);
        $result = $this->node_decorator->decorate($node);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Delete share.
     */
    public function deleteShare(string $id): Response
    {
        $node = $this->fs->getNode($id, Collection::class);
        $result = $node->unshare();

        if ($result === $node) {
            return (new Response())->setCode(204);
        }

        $result = $this->node_decorator->decorate($result);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Create collection.
     */
    public function post(
        string $name,
        ?string $id = null,
        array $attributes = [],
        int $conflict = 0
    ): Response {
        $attributes = $this->_verifyAttributes($attributes);
        $parent = $this->fs->getNode($id, null, false, true);
        $result = $parent->addDirectory((string) $name, $attributes, $conflict);
        $result = $this->node_decorator->decorate($result);

        return (new Response())->setCode(201)->setBody($result);
    }
}
