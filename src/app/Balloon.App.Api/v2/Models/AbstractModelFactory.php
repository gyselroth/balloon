<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2\Models;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Server;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

class AbstractModelFactory implements ModelFactoryInterface
{
    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $attributes = [];


    /**
     * Decorate attributes.
     */
    public function decorate(ResourceInterface $resource, ServerRequestInterface $request): array
    {
        $requested = $request->getQueryParams()['attributes'] ?? [];
        $attributes = $resource->toArray();

        $attrs = array_merge(
            $this->getMeta($resource, $attributes),
            $this->getAttributes($resource, $request),
            $this->attributes
        );

        if (0 === count($requested)) {
            return $this->translateAttributes($resource, $attrs);
        }

        return $this->translateAttributes($resource, array_intersect_key($attrs, array_flip($requested)));
    }

    /**
     * Add decorator.
     */
    public function addAttribute(string $attribute, Closure $decorator): self
    {
        $this->attributes[$attribute] = $decorator;
        return $this;
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(ResourceInterface $resource, ServerRequestInterface $request): array
    {
        return $resource->toArray();
    }

    /**
     * Get Attributes.
     */
    protected function getMeta(ResourceInterface $resource, array $attributes): array
    {
        return [
            'id' => (string)$attributes['_id'],
            'kind' => $resource->getKind(),
            'created' => function ($resource) use ($attributes) {
                return $attributes['created']->toDateTime()->format('c');
            },
            'changed' => function ($resource) use ($attributes) {
                return $attributes['changed']->toDateTime()->format('c');
            },
            'deleted' => function ($resource) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return null;
                }

                return $attributes['deleted']->toDateTime()->format('c');
            },
        ];
    }

    /**
     * Execute closures.
     */
    protected function translateAttributes(ResourceInterface $resource, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $value = $value->call($this, $resource);
            }
        }

        return $attributes;
    }
}
