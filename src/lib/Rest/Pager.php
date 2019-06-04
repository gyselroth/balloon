<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest;

use Balloon\Resource\ResourceInterface;
use Generator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class Pager
{
    /**
     * Pager.
     */
    public static function fromRequest(iterable $data, ServerRequestInterface $request): array
    {
        $query = array_merge([
            'offset' => 0,
            'limit' => 20,
        ], $request->getQueryParams());

        $nodes = [];
        $count = 0;

        foreach ($data as $resource) {
            ++$count;

            if ($resource instanceof ResourceInterface) {
                $nodes[] = $resource->decorate($request);
            } else {
                $nodes[] = (array) $resource;
            }
        }

        if ($data instanceof Generator) {
            $total = $data->getReturn();
        } else {
            $total = null;
        }

        $data = [
            'kind' => 'List',
            '_links' => self::getLinks((int) $query['offset'], (int) $query['limit'], $request->getUri(), $total),
            'count' => $count,
            'total' => $total,
            'data' => $nodes,
        ];

        return $data;
    }

    /**
     * Get paging links.
     */
    protected static function getLinks(int $offset, int $limit, UriInterface $uri, int $total): array
    {
        $links = [
            'self' => ['href' => (string) $uri->withQuery('offset='.$offset).'&limit='.$limit],
        ];

        if ($offset > 0) {
            $new_offset = $offset - $offset;
            if ($new_offset < 0) {
                $new_offset = 0;
            }

            $links['prev'] = [
                'href' => (string) $uri->withQuery('offset='.(string) $new_offset.'&limit='.$limit),
            ];
        }

        if ($offset + $limit < $total) {
            $links['next'] = [
                'href' => (string) $uri->withQuery('offset='.(string) ($offset + $limit).'&limit='.$limit),
            ];
        }

        return $links;
    }
}
