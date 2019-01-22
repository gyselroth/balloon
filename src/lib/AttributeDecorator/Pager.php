<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\AttributeDecorator;

use Generator;

class Pager
{
    /**
     * Decorator.
     *
     * @var AttributeDecoratorInterface
     */
    protected $decorator;

    /**
     * Data.
     *
     * @var iterable
     */
    protected $data;

    /**
     * Attributes.
     *
     * @var array
     */
    protected $attributes;

    /**
     * Offset.
     *
     * @var int
     */
    protected $offset;

    /**
     * Limit.
     *
     * @var int
     */
    protected $limit;

    /**
     * Total.
     *
     * @var int
     */
    protected $total;

    /**
     * URI.
     *
     * @var string
     */
    protected $uri;

    /**
     * Init.
     *
     * @param int $total
     */
    public function __construct(AttributeDecoratorInterface $decorator, Iterable $data, array $attributes, int $offset, int $limit, string $uri, ?int $total = null)
    {
        $this->decorator = $decorator;
        $this->data = $data;
        $this->attributes = $attributes;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->total = $total;
        $this->uri = $uri;
    }

    /**
     * Pagin.
     */
    public function paging(): array
    {
        $nodes = [];
        $count = 0;

        foreach ($this->data as $node) {
            ++$count;
            $nodes[] = $this->decorator->decorate($node, $this->attributes);
        }

        if ($this->total === null && $this->data instanceof Generator) {
            $this->total = $this->data->getReturn();
        }

        $data = [
            '_links' => $this->getLinks($count),
            'count' => $count,
            'total' => $this->total,
            'data' => $nodes,
        ];

        return $data;
    }

    /**
     * Get paging links.
     */
    protected function getLinks(int $count): array
    {
        $links = [
            'self' => ['href' => $this->uri.'?offset='.$this->offset.'&limit='.$this->limit],
        ];

        if ($this->offset > 0) {
            $offset = $this->offset - $this->offset;
            if ($offset < 0) {
                $offset = 0;
            }

            $links['prev'] = [
                'href' => $this->uri.'?offset='.$offset.'&limit='.$this->limit,
            ];
        }

        if ($this->offset + $count < $this->total) {
            $links['next'] = [
                'href' => $this->uri.'?offset='.($this->offset + $count).'&limit='.$this->limit,
            ];
        }

        return $links;
    }
}
