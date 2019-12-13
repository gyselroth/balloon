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
            return $node_model_factory->decorate($collection_factory->getOne($request->getAttribute('identity'), $value['id']), $request);
        });

        $this->addEmbedded('parent', function($value, $request, $resource) use($collection_factory, $node_model_factory) {
            $node = $resource->toArray()['node']['parent'] ?? null;

            if($node === null) {
                return null;
            }

            return $node_model_factory->decorate($collection_factory->getOne($request->getAttribute('identity'), $node), $request);
        });

        $this->addEmbedded('share', function($value, $request) use($collection_factory, $node_model_factory) {
            $node = $resource->toArray()['node']['share'] ?? null;

            if($node === null) {
                return null;
            }

            return $node_model_factory->decorate($collection_factory->getOne($request->getAttribute('identity'), $node), $request);
        });
    }


    /**
     * Get event Attributes.
     */
    protected function getAttributes(ResourceInterface $event, ServerRequestInterface $request): array
    {
        $attributes = $event->toArray();

        $result = [
            'operation' => $attributes['operation'] ?? '',
            'owner' => (string)$attributes['owner'],
            'node' => [
                'id' => (string)$attributes['node']['id'],
                'path' => (string)$attributes['node']['path'],
                'parent' => isset($attributes['node']['parent']) ? (string)$attributes['node']['parent'] : null,
                'share' => isset($attributes['node']['share']) ? (string)$attributes['node']['share'] : null,
                'name' => (string)$attributes['node']['name'],
            ]
        ];

        return $result;
    }
}

