<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Closure;

class DeltaAttributeDecorator implements AttributeDecoratorInterface
{
    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $custom = [];

    /**
     * Decorate attributes.
     *
     * @param array $node
     */
    public function decorate($node, array $attributes = []): array
    {
        if (0 === count($attributes)) {
            return $this->translateAttributes($node, $this->getAttributes($node));
        }

        return $this->translateAttributes($node, array_intersect_key($this->getAttributes($node), array_flip($attributes)));
    }

    /**
     * Add decorator.
     *
     *
     * @return DeltaAttributeDecorator
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     */
    protected function getAttributes(array $node): array
    {
        return [
            'id' => (string) $node['id'],
            'deleted' => $node['deleted'],
            'changed' => $node['changed']->toDateTime()->format('c'),
            'path' => $node['path'],
            'directory' => $node['directory'],
        ];
    }

    /**
     * Execute closures.
     */
    protected function translateAttributes(array $node, array $attributes): array
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
