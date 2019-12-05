<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2\Models;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

class SessionFactory extends AbstractModelFactory
{
    /**
     * Get session Attributes.
     */
    protected function getAttributes(ResourceInterface $session, ServerRequestInterface $request): array
    {
        return [];
    }
}

