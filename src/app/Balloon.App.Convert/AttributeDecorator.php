<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Server;
use Closure;

class AttributeDecorator implements AttributeDecoratorInterface
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Node decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $custom = [];

    /**
     * Init.
     */
    public function __construct(Server $server, NodeAttributeDecorator $node_decorator)
    {
        $this->server = $server;
        $this->node_decorator = $node_decorator;
    }

    /**
     * Decorate attributes.
     */
    public function decorate(array $slave, array $attributes = []): array
    {
        $requested = $attributes;
        $attrs = $this->getAttributes($slave);

        if (0 === count($requested)) {
            return $this->translateAttributes($slave, $attrs);
        }

        return $this->translateAttributes($slave, array_intersect_key($attrs, array_flip($requested)));
    }

    /**
     * Add decorator.
     *
     *
     * @return AttributeDecorator
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(array $slave): array
    {
        $fs = $this->server->getFilesystem();
        $node_decorator = $this->node_decorator;

        return [
            'id' => (string) $slave['_id'],
            'format' => (string) $slave['format'],
            'master' => function ($slave) use ($fs, $node_decorator) {
                try {
                    return $node_decorator->decorate($fs->findNodeById($slave['master']), ['_links', 'id', 'name']);
                } catch (\Exception $e) {
                    return null;
                }
            },
            'slave' => function ($slave) use ($fs, $node_decorator) {
                if (!isset($slave['slave'])) {
                    return null;
                }

                try {
                    return $node_decorator->decorate($fs->findNodeById($slave['slave']), ['_links', 'id', 'name']);
                } catch (\Exception $e) {
                    return null;
                }
            },
        ];
    }

    /**
     * Execute closures.
     */
    protected function translateAttributes(array $slave, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value->call($this, $slave);

                if (null === $result) {
                    unset($attributes[$key]);
                } else {
                    $value = $result;
                }
            } elseif ($value === null) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }
}
