<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem\Node\CollectionInterface;
use Balloon\Filesystem\Node\FileInterface;
use Balloon\Filesystem\Node\NodeInterface;
use Closure;

class NodeAttributeDecorator
{
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

        $node_attributes = $node->getAttributes();
        $attrs = array_merge(
            $this->getAttributes($node, $node_attributes),
            $this->getTypeAttributes($node, $node_attributes)
        );

        if (0 === count($attributes)) {
            return $this->translateAttributes($node, $attrs, $attributes);
        }

        return $this->translateAttributes($node, array_intersect_key($attrs, array_flip($attributes)), $attributes);
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
        return [
            'name' => (string) $attributes['name'],
            'mime' => (string) $attributes['mime'],
            'readonly' => (bool) $attributes['readonly'],
            'directory' => $node instanceof CollectionInterface,
            'meta' => $node->getMetaAttributes(),
            'size' => $node->getSize(),
            'parent' => function ($node, $requested) use ($attributes) {
                $parent = $node->getParent();

                if (null === $parent || $parent->isRoot()) {
                    return null;
                }

                return (string) $attributes['parent'];
            },
            'owner' => (string) $attributes['owner'],
            'share' => (string) $attributes['shared'],
            'created' => $attributes['created']->toDateTime()->format('c'),
            'changed' => $attributes['changed']->toDateTime()->format('c'),
            'deleted' => function ($node, $requested) use ($attributes) {
                if (!$attributes['deleted']) {
                    return null;
                }

                return $attributes['deleted']->toDateTime()->format('c');
            },
            'destroy' => function ($node, $requested) use ($attributes) {
                if (!$attributes['destroy']) {
                    return null;
                }

                return $attributes['destroy']->toDateTime()->format('c');
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
        if ($node instanceof FileInterface) {
            return [
                'version' => $attributes['version'],
                'hash' => $attributes['hash'],
            ];
        }

        return [
            'shared' => $node->isShared(),
            'reference' => $node->isReference(),
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
