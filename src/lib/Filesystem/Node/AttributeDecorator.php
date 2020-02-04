<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;

class AttributeDecorator implements AttributeDecoratorInterface
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Role decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $custom = [];

    /**
     * Init.
     */
    public function __construct(Server $server, Acl $acl, RoleAttributeDecorator $role_decorator)
    {
        $this->server = $server;
        $this->acl = $acl;
        $this->role_decorator = $role_decorator;
    }

    /**
     * Decorate attributes.
     */
    public function decorate(NodeInterface $node, array $attributes = []): array
    {
        $requested = $attributes;
        $attributes = $node->getAttributes();

        $attrs = array_merge(
            $this->getAttributes($node, $attributes),
            $this->getTimeAttributes($node, $attributes),
            $this->getTypeAttributes($node, $attributes),
            $this->custom
        );

        if (0 === count($requested)) {
            return $this->translateAttributes($node, $attrs);
        }

        return $this->translateAttributes($node, array_intersect_key($attrs, array_flip($requested)));
    }

    /**
     * Add decorator.
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(NodeInterface $node, array $attributes): array
    {
        $acl = $this->acl;
        $server = $this->server;
        $fs = $this->server->getFilesystem();
        $decorator = $this->role_decorator;

        return [
            'id' => (string) $attributes['_id'],
            'name' => (string) $attributes['name'],
            'mime' => (string) $attributes['mime'],
            'readonly' => (bool) $attributes['readonly'],
            'directory' => $node instanceof Collection,
            'meta' => function ($node) {
                return (object) $node->getMetaAttributes();
            },
            'size' => function ($node) {
                return $node->getSize();
            },
            'path' => function ($node) {
                try {
                    return $node->getPath();
                } catch (\Exception $e) {
                    return null;
                }
            },
            'parent' => function ($node) {
                $parent = $node->getParent();

                if (null === $parent || $parent->isRoot()) {
                    return null;
                }

                return $this->decorate($node->getParent(), ['id', 'name', '_links']);
            },
            'access' => function ($node) use ($acl) {
                return $acl->getAclPrivilege($node);
            },
            'lock' => function ($node) use ($server, $decorator, $attributes) {
                if (!$node->isLocked()) {
                    return null;
                }

                $lock = $attributes['lock'];

                try {
                    $user = $decorator->decorate(
                        $server->getUserById($lock['owner']),
                        ['id', 'name', '_links']
                    );
                } catch (\Exception $e) {
                    $user = null;
                }

                $lock = $attributes['lock'];

                return [
                    'owner' => $user,
                    'created' => $lock['created']->toDateTime()->format('c'),
                    'expire' => $lock['expire']->toDateTime()->format('c'),
                    'id' => $lock['id'],
                ];
            },
            'share' => function ($node) use ($fs) {
                if ($node->isShared() || !$node->isSpecial()) {
                    return null;
                }

                try {
                    return $this->decorate($fs->findNodeById($node->getShareId(true)), ['id', 'name', '_links']);
                } catch (\Exception $e) {
                    return null;
                }
            },
            'sharename' => function ($node) {
                if (!$node->isShared()) {
                    return null;
                }

                try {
                    return $node->getShareName();
                } catch (\Exception $e) {
                    return null;
                }
            },
            'shareowner' => function ($node) use ($server, $fs, $decorator) {
                if (!$node->isSpecial()) {
                    return null;
                }

                try {
                    return $decorator->decorate(
                        $server->getUserById($fs->findRawNode($node->getShareId())['owner']),
                        ['id', 'name', '_links']
                    );
                } catch (\Exception $e) {
                    return null;
                }
            },
            'owner' => function ($node) use ($server, $decorator) {
                try {
                    return $decorator->decorate(
                        $server->getUserById($node->getOwner()),
                        ['id', 'name', '_links']
                    );
                } catch (\Exception $e) {
                    return null;
                }
            },
        ];
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     */
    protected function getTimeAttributes(NodeInterface $node, array $attributes): array
    {
        return [
            'created' => function ($node) use ($attributes) {
                return $attributes['created']->toDateTime()->format('c');
            },
            'changed' => function ($node) use ($attributes) {
                return $attributes['changed']->toDateTime()->format('c');
            },
            'deleted' => function ($node) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return null;
                }

                return $attributes['deleted']->toDateTime()->format('c');
            },
            'destroy' => function ($node) use ($attributes) {
                if (null === $attributes['destroy']) {
                    return null;
                }

                return $attributes['destroy']->toDateTime()->format('c');
            },
        ];
    }

    /**
     * Get Attributes.
     */
    protected function getTypeAttributes(NodeInterface $node, array $attributes): array
    {
        $server = $this->server;
        $fs = $this->server->getFilesystem();

        if ($node instanceof File) {
            return [
                'version' => $attributes['version'],
                'hash' => $attributes['hash'],
            ];
        }

        return [
            'shared' => $node->isShared(),
            'reference' => $node->isReference(),
            'filter' => function ($node) use ($attributes) {
                if (null === $attributes['filter']) {
                    return null;
                }

                return json_decode($attributes['filter'], true, 512, JSON_THROW_ON_ERROR);
            },
            'mount' => function ($node) use ($fs, $attributes) {
                $mount = $node->getAttributes()['mount'];

                if (!$node->isMounted() && !$node->isReference()) {
                    return null;
                }

                if ($node->isReference()) {
                    $attributes = $fs->findRawNode($node->getShareId());
                    if (isset($attributes['mount']) && count($attributes['mount']) > 0) {
                        $mount = $attributes['mount'];
                        unset($mount['username'], $mount['password']);

                        return $mount;
                    }

                    return null;
                }

                if (!empty($mount['password'])) {
                    unset($mount['password']);
                    $mount['has_password'] = true;
                }

                return $mount;
            },
        ];
    }

    /**
     * Execute closures.
     */
    protected function translateAttributes(NodeInterface $node, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value->call($this, $node);

                if (null === $result) {
                    unset($attributes[$key]);
                } else {
                    $value = $result;
                }
            } elseif ($value === null) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }
}
