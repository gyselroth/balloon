<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem;
use Balloon\Helper;
use Balloon\Server;
use Closure;
use MongoDB\BSON\UTCDateTime;

class AttributeDecorator
{
    /**
     * Server
     *
     * @var Server
     */
    protected $server;


    /**
     * Filesystem
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Acl
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Custom attributes
     *
     * @var array
     */
    protected $custom = [];


    /**
     * Init
     *
     * @param Server $server
     * @param Acl $acl
     */
    public function __construct(Server $server, Acl $acl)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->acl = $acl;
    }

    /**
     * Decorate attributes
     *
     * @param NodeInterface $node
     * @param array $attributes
     * @return array
     */
    public function decorate(NodeInterface $node, ?array $attributes): array
    {
        if($attributes === null) {
            $attributes = [];
        }

        $requested = $this->prepare($node, $attributes);
        $attributes = $node->getAttributes();

        $attrs = array_merge(
            $this->getAttributes($node, $attributes, $requested),
            $this->getTimeAttributes($node, $attributes, $requested),
            $this->getTypeAttributes($node, $attributes, $requested),
            $this->custom
        );

        if (count($requested['attributes']) === 0) {
            return $this->translateAttributes($node, $attrs);
        }

        return $this->translateAttributes($node, array_intersect_key($attrs, array_flip($requested['attributes'])));
    }


    /**
     * Add decorator
     *
     * @param string $attribute
     * @param Closure $decorator
     * @return AttributeDecorator
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }


    /**
     * Prepare requested attributes
     *
     * @param NodeInterface $node
     * @param array $attributes
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
            'shareowner' => []
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

            if (('file' === $prefix || 'collection' === $prefix) && count($keys) === 1) {
                $clean['attributes'][] = $keys[0];
            } elseif(count($keys) === 0) {
                $clean['attributes'][] = $attr;
            } elseif ('meta' === $prefix && 1 === count($keys)) {
                $clean['attributes'][] = 'meta';
                $clean['meta'][] = $keys[0];
            } elseif(isset($clean[$prefix])) {
                $clean['attributes'][] = $prefix;
                $clean[$prefix][] = $attr;
            }
        }

        return $clean;
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     * @param array $attributes
     * @param array $requested
     *
     * @return array
     */
    protected function getAttributes(NodeInterface $node, array $attributes, ?array $requested): array
    {
        $acl = $this->acl;
        $server = $this->server;

        return [
            'id' => (string) $attributes['id'],
            'name' => (string) $attributes['name'],
            'mime' => (string) $attributes['mime'],
            'readonly' => (bool) $attributes['readonly'],
            'directory' => $node instanceof Collection,
            'meta' => function ($node) {
                return (object) $node->getMetaAttribute([]);
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
            'parent' => function ($node) use ($requested) {
                $parent = $node->getParent();

                if (null === $parent || $parent->isRoot()) {
                    return null;
                }

                return $this->decorate($node->getParent(), $requested['parent']);
            },
            'access' => function($node) use($acl) {
                return $acl->getAclPrivilege($node);
            },
            'owner' => function($node) use($server, $requested) {
                try {
                    return $server->getUserById($node->getOwner())->getAttribute($requested['owner']);
                } catch(\Exception $e) {
                    return null;
                }
            }
        ];
    }


    /**
     * Get Attributes.
     *
     * @param NodeInterface
     * @param array $attributes
     * @param array $requested
     *
     * @return array
     */
    protected function getTimeAttributes(NodeInterface $node, array $attributes, ?array $requested): array
    {
        return [
            'created' => function ($node) use ($attributes) {
                return Helper::DateTimeToUnix($attributes['created']);
            },
            'changed' => function ($node) use ($attributes) {
                return Helper::DateTimeToUnix($attributes['changed']);
            },
            'deleted' => function ($node) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return false;
                }

                return Helper::DateTimeToUnix($attributes['deleted']);
            },
            'destroy' => function ($node) use ($attributes) {
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
     * @param array $requested
     *
     * @return array
     */
    protected function getTypeAttributes(NodeInterface $node, array $attributes, ?array $requested): array
    {
        $server = $this->server;
        $fs = $this->fs;

        if($node instanceof File) {
            return [
                'version' => $attributes['version'],
                'hash' => $attributes['hash'],
            ];
        }

        return [
            'shared' => $node->isShared(),
            'reference' => $node->isReference(),
            'filter' => function($node) use($attributes) {
                if($attributes['filter'] === null) {
                    return null;
                }

                return json_decode($attributes['filter']);
            },
            'share' => function($node) use($requested) {
                if(!$node->isShare())  {
                    return null;
                }

                try {
                    return $this->decorate($node->getShareNode(), $requested['share']);
                } catch (\Exception $e) {
                    return null;
                }
            },
            'shareowner' => function($node) use($server, $fs, $requested) {
                if(!$node->isSpecial())  {
                    return null;
                }

                try {
                    return $server->getUserById($fs->findRawNode($node->getShareId())['owner'])
                        ->getAttribute($requested['shareowner']);
                } catch(\Exception $e) {
                    return null;
                }
            }
        ];
    }


    /**
     * Execute closures
     *
     * @param NodeInterface
     * @param array $attributes
     *
     * @return array
     */
    protected function translateAttributes(NodeInterface $node, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value($node);

                if($result === null) {
                    unset($attributes[$key]);
                } else {
                    $value = $result;
                }
            }
        }

        return $attributes;
    }
}
