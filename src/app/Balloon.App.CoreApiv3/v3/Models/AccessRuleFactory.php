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
use Balloon\AccessRule\Factory as AccessRuleResourceFactory;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Server\AttributeDecorator as RuleAttributeDecorator;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

class AccessRuleFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;


    public function __construct(AccessRuleResourceFactory $access_rule_factory)
    {
        $this->access_rule_factory = $access_rule_factory;
    }


    /**
     * Get access_rule Attributes.
     */
    protected function getAttributes(ResourceInterface $access_rule, ServerRequestInterface $request): array
    {
        $attributes = $access_rule->toArray();

        $result = [
            'verbs' => $attributes['verbs'],
            'roles' => $attributes['roles'],
            'resources' => $attributes['resources'],
            'as' => $attributes['as'],
        ];

        return $result;
    }
}

