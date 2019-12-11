<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Resource\AbstractResource;
use Balloon\Resource\AttributeResolver;
use Balloon\Group\GroupInterface;
use Psr\Http\Message\ServerRequestInterface;

class Group extends AbstractResource implements GroupInterface
{
    /**
     * Kind.
     */
    public const KIND = 'Group';

    /**
     * Initialize.
     */
    public function __construct(array $resource = [])
    {
        $this->resource = $resource;
    }

    public function getMembers(): array
    {
        return $this->resource['members'] ?? [];
    }
}
