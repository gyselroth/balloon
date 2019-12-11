<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3\v3\Models;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Group\Factory as GroupResourceFactory;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

class GroupFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    public function __construct(GroupResourceFactory $group_factory)
    {
        $this->group_factory = $group_factory;
    }


    /**
     * Get group Attributes.
     */
    protected function getAttributes(ResourceInterface $group, ServerRequestInterface $request): array
    {
        $attributes = $group->toArray();
        $quota = null;
        $group_factory = $this->group_factory;

        $result = [
            'name' => (string) $attributes['name'],
            //'admin' => (bool) $attributes['admin'],
            'namespace' => isset($attributes['namespace']) ? (string) $attributes['namespace'] : null,
        ];

        return $result;
    }
}

