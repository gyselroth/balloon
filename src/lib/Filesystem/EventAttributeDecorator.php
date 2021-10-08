<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;

class EventAttributeDecorator implements AttributeDecoratorInterface
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Node decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Role decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $custom = [];

    /**
     * Init.
     */
    public function __construct(Server $server, NodeAttributeDecorator $node_decorator, RoleAttributeDecorator $role_decorator)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->node_decorator = $node_decorator;
        $this->role_decorator = $role_decorator;
    }

    /**
     * Decorate attributes.
     */
    public function decorate(array $event, array $attributes = []): array
    {
        $requested = $attributes;

        $attrs = array_merge(
            $this->getAttributes($event),
            $this->custom
        );

        if (0 === count($requested)) {
            return $this->translateAttributes($event, $attrs);
        }

        return $this->translateAttributes($event, array_intersect_key($attrs, array_flip($requested)));
    }

    /**
     * Add decorator.
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(array $event): array
    {
        return [
            'event' => (string) $event['_id'],
            'timestamp' => $event['timestamp']->toDateTime()->format('c'),
            'operation' => $event['operation'],
            'name' => $event['name'],
            'client' => isset($event['client']) ? $event['client'] : null,
            'previous' => $this->getPrevious($event),
            'node' => $this->getNode($event),
            'share' => $this->getShare($event),
            'parent' => $this->getParent($event),
            'user' => $this->getUser($event),
        ];
    }

    /**
     * Get Attributes.
     */
    protected function getPrevious(array $event): ?array
    {
        if (!isset($event['previous'])) {
            return null;
        }

        $previous = $event['previous'];

        if (isset($previous['parent'])) {
            if ($previous['parent'] === null) {
                $previous['parent'] = [
                    'id' => null,
                    'name' => null,
                ];
            } else {
                try {
                    $node = $this->fs->findNodeById($previous['parent'], null, NodeInterface::DELETED_INCLUDE);
                    $previous['parent'] = $this->node_decorator->decorate($node, ['id', 'name', '_links']);
                } catch (\Exception $e) {
                    $previous['parent'] = null;
                }
            }
        }

        return $previous;
    }

    /**
     * Get Attributes.
     */
    protected function getNode(array $event): ?array
    {
        try {
            $node = $this->fs->findNodeById($event['node'], null, NodeInterface::DELETED_INCLUDE);

            return $this->node_decorator->decorate($node, ['id', 'name', 'deleted', '_links']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Attributes.
     */
    protected function getParent(array $event): ?array
    {
        try {
            if (null === $event['parent']) {
                return [
                    'id' => null,
                    'name' => null,
                ];
            }
            $node = $this->fs->findNodeById($event['parent'], null, NodeInterface::DELETED_INCLUDE);

            return $this->node_decorator->decorate($node, ['id', 'name', 'deleted', '_links']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Attributes.
     */
    protected function getUser(array $event): ?array
    {
        try {
            $user = $this->fs->getServer()->getUserById($event['owner']);

            return $this->role_decorator->decorate($user, ['id', 'name', '_links']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Attributes.
     */
    protected function getShare(array $event): ?array
    {
        try {
            if (isset($event['share']) && false === $event['share'] || !isset($event['share'])) {
                return null;
            }
            $node = $this->fs->findNodeById($event['share'], null, NodeInterface::DELETED_INCLUDE);

            return $this->node_decorator->decorate($node, ['id', 'name', 'deleted']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Execute closures.
     */
    protected function translateAttributes(array $event, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value->call($this, $event);

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
