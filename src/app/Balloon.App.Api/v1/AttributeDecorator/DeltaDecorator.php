<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1\AttributeDecorator;

use Balloon\Filesystem\Node\NodeInterface;
use Closure;
use MongoDB\BSON\UTCDateTime;
use stdClass;

class DeltaDecorator
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
     * @return AttributeDecorator
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
            'changed' => $this->dateTimeToUnix($node['changed']),
            'path' => $node['path'],
        ];
    }

    /**
     * Convert UTCDateTime to unix ts.
     *
     * @param UTCDateTime $date
     *
     * @return stdClass
     */
    protected function dateTimeToUnix(?UTCDateTime $date): ?stdClass
    {
        if (null === $date) {
            return null;
        }

        $date = $date->toDateTime();
        $ts = new stdClass();
        $ts->sec = $date->format('U');
        $ts->usec = $date->format('u');

        return $ts;
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
