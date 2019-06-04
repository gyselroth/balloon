<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Tubee;

use Psr\Http\Message\ServerRequestInterface;
use Tubee\AccessRule\AccessRuleInterface;
use Tubee\Resource\AbstractResource;
use Tubee\Resource\AttributeResolver;

class AccessRule extends AbstractResource implements AccessRuleInterface
{
    /**
     * Kind.
     */
    public const KIND = 'AccessRule';

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
            'kind' => 'AccessRule',
            'data' => $this->getData(),
       ];

        return AttributeResolver::resolve($request, $this, $resource);
    }
}
