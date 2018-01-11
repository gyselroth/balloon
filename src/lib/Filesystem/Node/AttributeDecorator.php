<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Helper;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;

class AttributeDecorator
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
     *
     * @param Server    $server
     * @param Acl       $acl
     * @param Decorator $role_decorator
     */
    public function __construct(Server $server, Acl $acl, RoleAttributeDecorator $role_decorator)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->acl = $acl;
        $this->role_decorator = $role_decorator;
    }

    /**
     * Decorate attributes.
     *
     * @param NodeInterface $node
     * @param array         $attributes
     *
     * @return array
     */
    public function decorate(NodeInterface $node, ?array $attributes = null): array
    {
        if (null === $attributes) {
            $attributes = [];
        }

        $requested = $this->prepare($node, $attributes);
        $attributes = $node->getAttributes();

        $attrs = array_merge(
            $this->getAttributes($node, $attributes),
            $this->getTimeAttributes($node, $attributes),
            $this->getTypeAttributes($node, $attributes),
            $this->custom
        );

        if (0 === count($requested['attributes'])) {
            return $this->translateAttributes($node, $attrs, $requested);
        }

        return $this->translateAttributes($node, array_intersect_key($attrs, array_flip($requested['attributes'])), $requested);
    }

    /**
     * Add decorator.
     *
     * @param string  $attribute
     * @param Closure $decorator
     *
     * @return AttributeDecorator
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    /**
     * Prepare requested attributes.
     *
     * @param NodeInterface $node
     * @param array         $attributes
     *
     * @return array
     */
    protected function prepare(NodeInterface $node, ?array $attributes): array
    {
        $clean = [
            'attributes' => [],
            'meta' => [],
            'share' => [],
            'parent' => [],
            'owner' => [],
            'shareowner' => [],
        ];

        foreach ($attributes as $key => $attr) {
            $keys = explode('.', $attr);
            $prefix = array_shift($keys);

            if ('file' === $prefix && ($node instanceof Collection)) {
                continue;
            }
            if ('collection' === $prefix && ($node instanceof File)) {
                continue;
            }
            if (('file' === $prefix || 'collection' === $prefix) && count($keys) > 1) {
                $prefix = array_shift($keys);
            }

            if (('file' === $prefix || 'collection' === $prefix) && 1 === count($keys)) {
                $clean['attributes'][] = $keys[0];
            } elseif (0 === count($keys)) {
                $clean['attributes'][] = $attr;
            } elseif ('meta' === $prefix && 1 === count($keys)) {
                $clean['attributes'][] = 'meta';
                $clean['meta'][] = $keys[0];
            } elseif (isset($clean[$prefix])) {
                $clean['attributes'][] = $prefix;
                $clean[$prefix][] = $keys[0];
            }
        }

        return $clean;
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     * @param array $attributes
     *
     * @return array
     */
    protected function getAttributes(NodeInterface $node, array $attributes): array
    {
        $acl = $this->acl;
        $server = $this->server;
        $fs = $this->fs;
        $decorator = $this->role_decorator;

        return [
            'id' => (string) $attributes['id'],
            'name' => (string) $attributes['name'],
            'mime' => (string) $attributes['mime'],
            'readonly' => (bool) $attributes['readonly'],
            'directory' => $node instanceof Collection,
            'meta' => function ($node, $requested) {
                return (object) $node->getMetaAttribute([]);
            },
            'size' => function ($node, $requested) {
                return $node->getSize();
            },
            'path' => function ($node, $requested) {
                try {
                    return $node->getPath();
                } catch (\Exception $e) {
                    return null;
                }
            },
            'parent' => function ($node, $requested) {
                $parent = $node->getParent();

                if (null === $parent || $parent->isRoot()) {
                    return null;
                }

                return $this->decorate($node->getParent(), $requested['parent']);
            },
            'access' => function ($node, $requested) use ($acl) {
                return $acl->getAclPrivilege($node);
            },
            'owner' => function ($node, $requested) use ($server, $decorator) {
                if ($node->isShare() || !$node->isSpecial()) {
                    return null;
                }

                try {
                    return $decorator->decorate($server->getUserById($node->getOwner()), $requested['owner']);
                } catch (\Exception $e) {
                    return null;
                }
            },
            'share' => function ($node, $requested) {
                if ($node->isShared() || !$node->isSpecial()) {
                    return null;
                }

                try {
                    return $this->decorate($node->getShareNode(), $requested['share']);
                } catch (\Exception $e) {
                    return null;
                }
            },
            'sharename' => function ($node, $requested) {
                if (!$node->isShared()) {
                    return null;
                }

                try {
                    return $node->getShareName();
                } catch (\Exception $e) {
                    return null;
                }
            },
            'shareowner' => function ($node, $requested) use ($server, $fs, $decorator) {
                if (!$node->isSpecial()) {
                    return null;
                }

                try {
                    return $decorator->decorate(
                        $server->getUserById($fs->findRawNode($node->getShareId())['owner']),
                        $requested['shareowner']
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
     * @param array $attributes
     *
     * @return array
     */
    protected function getTimeAttributes(NodeInterface $node, array $attributes): array
    {
        return [
            'created' => function ($node, $requested) use ($attributes) {
                return Helper::DateTimeToUnix($attributes['created']);
            },
            'changed' => function ($node, $requested) use ($attributes) {
                return Helper::DateTimeToUnix($attributes['changed']);
            },
            'deleted' => function ($node, $requested) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return false;
                }

                return Helper::DateTimeToUnix($attributes['deleted']);
            },
            'destroy' => function ($node, $requested) use ($attributes) {
                if (false === $attributes['destroy']) {
                    return false;
                }

                return Helper::DateTimeToUnix($attributes['destroy']);
            },
        ];
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     * @param array $attributes
     *
     * @return array
     */
    protected function getTypeAttributes(NodeInterface $node, array $attributes): array
    {
        $server = $this->server;
        $fs = $this->fs;

        if ($node instanceof File) {
            return [
                'version' => $attributes['version'],
                'hash' => $attributes['hash'],
            ];
        }

        return [
            'shared' => $node->isShared(),
            'reference' => $node->isReference(),
            'filter' => function ($node, $requested) use ($attributes) {
                if (null === $attributes['filter']) {
                    return null;
                }

                return json_decode($attributes['filter']);
            },
        ];
    }

    /**
     * Execute closures.
     *
     * @param NodeInterface
     * @param array $attributes
     * @param array $requested
     *
     * @return array
     */
    protected function translateAttributes(NodeInterface $node, array $attributes, array $requested): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value($node, $requested);

                if (null === $result) {
                    unset($attributes[$key]);
                } else {
                    $value = $result;
                }
            }
        }

        return $attributes;
    }
}
