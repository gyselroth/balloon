<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3\v3\Models;

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
     * Embedded resources.
     *
     * @var array
     */
    protected $embedded = [];

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
            return $this->translateAttributes($resource, $attrs, $request);
        }

        return $this->translateAttributes($resource, array_intersect_key($attrs, array_flip($requested)), $request);
    }

    /**
     * Add embedded resource.
     */
    public function addEmbedded(string $attribute, Closure $decorator): self
    {
        $this->embedded[$attribute] = $decorator;
        return $this;
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
            '_links' => [],
            '_embedded' => [],
            'meta' => [
                'annotations' => [],
                'labels' => [],
            ],
            'created' => function ($resource) use ($attributes) {
                return $attributes['created']->toDateTime()->format('c');
            },
            'changed' => function ($resource) use ($attributes) {
                if (!isset($attributes['changed'])) {
                    return null;
                }

                return $attributes['changed']->toDateTime()->format('c');
            },
            'deleted' => function ($resource) use ($attributes) {
                if (!isset($attributes['deleted'])) {
                    return null;
                }

                return $attributes['deleted']->toDateTime()->format('c');
            },
        ];
    }

    /**
     * Execute closures.
     */
    protected function translateAttributes(ResourceInterface $resource, array $attributes, ServerRequestInterface $request): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                try {
                    $value = $value->call($this, $resource);
                } catch(\Exception $e) {
                    $value = null;
                }
            }
        }

        $params = $request->getQueryParams();
        $sub_request = $request->withQueryParams(['attributes' => array_merge(['id', 'name', '_links'], $params['attributes'] ?? [])]);
        $orig = $resource->toArray();

        foreach($this->embedded as $key => $value) {
            try {
                $resolved = $value($orig[$key] ?? null, $sub_request, $resource);
                if($resolved !== null) {
                    $attributes['_embedded'][$key] = $resolved;
                }
            } catch(\Exception $e) {

            }
        }

        return $attributes;
    }
}
