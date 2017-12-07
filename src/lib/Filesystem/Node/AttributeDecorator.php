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
use Balloon\Helper;
use Balloon\Server;
use Closure;
use MongoDB\BSON\UTCDateTime;

class AttributeDecorator
{
    protected $custom = [];

    public function __construct(Server $server, Acl $acl)
    {
        $this->fs = $server->getFilesystem();
        $this->acl = $acl;
    }

    public function decorate(NodeInterface $node, ?array $attributes): array
    {
        return $this->getAttributes($node, $this->prepare($node, $attributes));
    }

    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    protected function prepare(NodeInterface $node, $attributes)
    {
        /*
        file.
        collection.
        meta.
        share.
        parent.
        */

        $meta = [];
        $clean = [];

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

            if ('meta' === $prefix && 1 === count($keys)) {
                $meta[] = $keys[0];
            } elseif (1 === count($keys)) {
                $clean[] = $keys[0];
            } else {
                $clean[] = $attr;
            }
        }

        if (count($meta) > 0) {
            $clean[] = 'meta';
        }

        $attributes = $clean;
    }

    /**
     * Get Attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function getAttributes(NodeInterface $node, ?array $requested): array
    {
        $attributes = $node->getAttributes();

        $attrs = [
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
            'path' => function ($node) {
                try {
                    return $node->getPath();
                } catch (\Exception $e) {
                    return null;
                }
            },
            'parent' => function ($node) use ($requested) {
                $parent = $node->getParent();

                if (null === $parent) {
                    return null;
                }

                return $this->getAttributes($node->getParent(), $requested);
            },
        ];

        $attrs = array_merge($attrs, $this->custom);

        if (null === $requested) {
            return $this->translateAttributes($node, $attrs);
        }

        return $this->translateAttributes($node, array_intersect_key($attrs, array_flip($requested)));
    }

    protected function translateAttributes(NodeInterface $node, array $attributes): array
    {
        foreach ($attributes as &$value) {
            if ($value instanceof Closure) {
                $value = $value($node);
            }
        }

        return $attributes;
    }

    /*
    
            try {
                $sharenode = $this->getShareNode();
            } catch (\Exception $e) {
                $sharenode = null;
            }
    
            $build = [];
    
            foreach ($attributes as $key => $attr) {
                switch ($attr) {
                    case 'id':
                        $build['id'] = (string) $this->_id;
    
                    break;
                    case 'name':
                    case 'mime':
                    case 'readonly':
                    case 'directory':
                        $build[$attr] = $this->{$attr};
    
                    break;
                    case 'parent':
                        try {
                            $parent = $this->getParent();
                            if (null === $parent || null === $parent->getId()) {
                                $build[$attr] = null;
                            } else {
                                $build[$attr] = (string) $parent->getId();
                            }
                        } catch (\Exception $e) {
                            $build[$attr] = null;
                        }
    
                    break;
                    case 'meta':
                        $build['meta'] = (object) $this->getMetaAttribute($meta);
    
                    break;
                    case 'size':
                        $build['size'] = $this->getSize();
    
                    break;
                    case 'deleted':
                    case 'changed':
                    case 'created':
                    case 'destroy':
                        if ($this->{$attr} instanceof UTCDateTime) {
                            $build[$attr] = Helper::DateTimeToUnix($this->{$attr});
                        } else {
                            $build[$attr] = $this->{$attr};
                        }
    
                    break;
                    case 'path':
                        try {
                            $build['path'] = $this->getPath();
                        } catch (\Balloon\Exception\NotFound $e) {
                            $build['path'] = null;
                        }
    
                    break;
                    case 'shared':
                        if (true === $this->directory) {
                            $build['shared'] = $this->isShared();
                        }
    
                    break;
                    case 'filtered':
                        if (true === $this->directory) {
                            $build['filtered'] = $this->isCustomFilter();
                        }
    
                    break;
                    case 'reference':
                        if (true === $this->directory) {
                            $build['reference'] = $this->isReference();
                        }
    
                    break;
                    case 'share':
                        if ($this->isSpecial() && null !== $sharenode) {
                            $build['share'] = $sharenode->getName();
                        } else {
                            $build['share'] = false;
                        }
    
                    break;
                    case 'access':
                        if ($this->isSpecial() && null !== $sharenode) {
                            $build['access'] = $this->_acl->getAclPrivilege($sharenode);
                        }
    
                    break;
                    case 'shareowner':
                        if ($this->isSpecial() && null !== $sharenode) {
                            $build['shareowner'] = $this->_server->getUserById($this->_fs->findRawNode($this->getShareId())['owner'])
                              ->getUsername();
                        }
    
                    break;
                }
            }
    
            return $build;
    */
}
