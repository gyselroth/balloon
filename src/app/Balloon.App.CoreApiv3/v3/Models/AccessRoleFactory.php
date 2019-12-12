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
use Balloon\AccessRole\Factory as AccessRoleResourceFactory;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

class AccessRoleFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;


    public function __construct(AccessRoleResourceFactory $access_role_factory)
    {
        $this->access_role_factory = $access_role_factory;
    }


    /**
     * Get access_role Attributes.
     */
    protected function getAttributes(ResourceInterface $access_role, ServerRequestInterface $request): array
    {
        $attributes = $access_role->toArray();

        $result = [
            'selectors' => $attributes['selectors'],
        ];

        return $result;
    }
}

