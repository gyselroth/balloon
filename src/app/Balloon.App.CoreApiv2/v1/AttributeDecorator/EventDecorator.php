<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1\AttributeDecorator;

use Balloon\Filesystem;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Closure;
use MongoDB\BSON\UTCDateTime;
use stdClass;

class EventDecorator
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
     * @var NodeDecorator
     */
    protected $node_decorator;

    /**
     * Role decorator.
     *
     * @var RoleDecorator
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
    public function __construct(Server $server, NodeDecorator $node_decorator, RoleDecorator $role_decorator)
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
     * Convert UTCDateTime to unix ts.
     *
     * @param UTCDateTime $date
     *
     * @return stdClass
     */
    protected function dateTimeToUnix(?UTCDateTime $date): ?stdClass
    {
        if (null === $date) {
            return null;
        }

        $date = $date->toDateTime();
        $ts = new stdClass();
        $ts->sec = $date->format('U');
        $ts->usec = $date->format('u');

        return $ts;
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(array $event): array
    {
        return [
            'event' => (string) $event['_id'],
            'timestamp' => $this->dateTimeToUnix($event['timestamp']),
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
     *
     *
     * @return array
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
     *
     *
     * @return array
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
     *
     *
     * @return array
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
     *
     *
     * @return array
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
     *
     *
     * @return array
     */
    protected function getShare(array $event)// : ?array
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
     *
     * @param NodeInterface
     */
    protected function translateAttributes(array $event, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value->call($this, $event);

                if (null === $result) {
                    //unset($attributes[$key]);
                } else {
                    $value = $result;
                }
            } elseif ($value === null) {
                //unset($attributes[$key]);
            }
        }

        return $attributes;
    }
}
