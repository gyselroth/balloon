<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3\v3\Models;

use Balloon\Resource\ResourceInterface;
use Psr\Http\Message\ServerRequestInterface;
use Balloon\Collection\Factory as CollectionFactory;
use Balloon\User\Factory as UserFactory;
use Balloon\App\CoreApiv3\v3\Models\NodeFactory as NodeModelFactory;
use Balloon\App\CoreApiv3\v3\Models\UserFactory as UserModelFactory;

class EventFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;


    public function __construct(CollectionFactory $collection_factory, NodeModelFactory $node_model_factory, UserFactory $user_factory, UserModelFactory $user_model_factory)
    {
        $this->addEmbedded('owner', function($value, $request) use($user_factory, $user_model_factory) {
            return $user_model_factory->decorate($user_factory->getOne($value), $request);
        });

        $this->addEmbedded('node', function($value, $request) use($collection_factory, $node_model_factory) {
            return $node_model_factory->decorate($collection_factory->getOne($request->getAttribute('identity'), $value), $request);
        });

        $this->addEmbedded('parent', function($value, $request) use($collection_factory, $node_model_factory) {
            return $node_model_factory->decorate($collection_factory->getOne($request->getAttribute('identity'), $value), $request);
        });

        $this->addEmbedded('share', function($value, $request) use($collection_factory, $node_model_factory) {
            return $node_model_factory->decorate($collection_factory->getOne($request->getAttribute('identity'), $value), $request);
        });
    }


    /**
     * Get event Attributes.
     */
    protected function getAttributes(ResourceInterface $event, ServerRequestInterface $request): array
    {
        $attributes = $event->toArray();

        $result = [
            'node' => (string)$attributes['node'],
            'node_name' => $attributes['name'],
            'owner' => isset($attributes['owner']) ? (string)$attributes['owner'] : null,
            'parent' => isset($attributes['parent']) ? (string)$attributes['parent'] : null,
            'share' => isset($attributes['share']) ? (string)$attributes['share'] : null,
        ];

        return $result;
    }
}

