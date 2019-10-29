<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Psr\Http\Message\ServerRequestInterface;
use Balloon\AccessRole\AccessRoleInterface;
use Balloon\Resource\AbstractResource;
use Balloon\Resource\AttributeResolver;

class AccessRole extends AbstractResource implements AccessRoleInterface
{
    /**
     * Kind.
     */
    public const KIND = 'AccessRole';

    /**
     * Data object.
     */
    public function __construct(array $resource)
    {
        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function decorate(ServerRequestInterface $request): array
    {
        $resource = [
            '_links' => [
                'self' => ['href' => (string) $request->getUri()],
            ],
            'kind' => 'AccessRole',
            'data' => $this->getData(),
        ];

        return AttributeResolver::resolve($request, $this, $resource);
    }
}
