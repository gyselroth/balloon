<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
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
     * Custom attributes.
     *
     * @var array
     */
    protected $custom = [];

    /**
     * Init.
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Decorate attributes.
     *
     * @param array $attributes
     */
    public function decorate(RoleInterface $role, ?array $attributes = null): array
    {
        if (null === $attributes) {
            $attributes = [];
        }

        $role_attributes = $role->getAttributes();

        $attrs = array_merge(
            $this->getAttributes($role, $role_attributes),
            $this->getUserAttributes($role, $role_attributes),
            $this->getGroupAttributes($role, $role_attributes),
            $this->custom
        );

        if (0 === count($attributes)) {
            return $this->translateAttributes($role, $attrs);
        }

        return $this->translateAttributes($role, array_intersect_key($attrs, array_flip($attributes)));
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
     *
     * @param RoleInterface
     */
    protected function getAttributes(RoleInterface $role, array $attributes): array
    {
        $user = $this->server->getIdentity();
        if ($user === null || $attributes['_id'] != $user->getId() && !$user->isAdmin()) {
            return [];
        }

        return [
            'created' => function ($role) use ($attributes) {
                return $attributes['created']->toDateTime()->format('c');
            },
            'changed' => function ($role) use ($attributes) {
                return $attributes['changed']->toDateTime()->format('c');
            },
            'deleted' => function ($role) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return null;
                }

                return $attributes['deleted']->toDateTime()->format('c');
            },
        ];
    }

    /**
     * Get group Attributes.
     *
     * @param RoleInterface
     */
    protected function getGroupAttributes(RoleInterface $role, array $attributes): array
    {
        if (!($role instanceof Group)) {
            return [];
        }

        return [
            'id' => (string) $attributes['_id'],
            'name' => $attributes['name'],
            'namespace' => isset($attributes['namespace']) ? (string) $attributes['namespace'] : null,
        ];
    }

    /**
     * Get user Attributes.
     *
     * @param RoleInterface
     */
    protected function getUserAttributes(RoleInterface $role, array $attributes): array
    {
        if (!($role instanceof User)) {
            return [];
        }

        $user = $this->server->getIdentity();
        $quota = null;

        $result = [
            'id' => (string) $attributes['_id'],
            'username' => (string) $attributes['username'],
            'name' => (string) $attributes['username'],
            'admin' => (bool) $attributes['admin'],
            'namespace' => isset($attributes['namespace']) ? (string) $attributes['namespace'] : null,
            'mail' => function ($role) use ($attributes, $user) {
                if (!isset($attributes['mail'])) {
                    return null;
                }

                if ($attributes['_id'] == $user->getId() || $user->isAdmin()) {
                    return (string) $attributes['mail'];
                }

                return null;
            },
            'locale' => isset($attributes['locale']) ? (string) $attributes['locale'] : null,
            'hard_quota' => isset($attributes['hard_quota']) ? (int) $attributes['hard_quota'] : null,
            'soft_quota' => isset($attributes['soft_quota']) ? (int) $attributes['soft_quota'] : null,
            'available' => function ($role) use (&$quota, $attributes, $user) {
                $quota === null ? $quota = $role->getQuotaUsage() : null;
                if ($attributes['_id'] == $user->getId() || $user->isAdmin()) {
                    return $quota['available'];
                }

                return null;
            },
            'used' => function ($role) use (&$quota, $attributes, $user) {
                $quota === null ? $quota = $role->getQuotaUsage() : null;
                if ($attributes['_id'] == $user->getId() || $user->isAdmin()) {
                    return $quota['used'];
                }

                return null;
            },
        ];

        return $result;
    }

    /**
     * Execute closures.
     *
     * @param RoleInterface
     */
    protected function translateAttributes(RoleInterface $role, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value($role);

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
