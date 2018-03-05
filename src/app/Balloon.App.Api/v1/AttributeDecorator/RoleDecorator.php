<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1\AttributeDecorator;

use Balloon\Server;
use Balloon\Server\Group;
use Balloon\Server\RoleInterface;
use Balloon\Server\User;
use Closure;
use MongoDB\BSON\Binary;

class RoleDecorator
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
     *
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Decorate attributes.
     *
     * @param RoleInterface $role
     * @param array         $attributes
     *
     * @return array
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
            return $this->translateAttributes($role, $attrs, $attributes);
        }

        return $this->translateAttributes($role, array_intersect_key($attrs, array_flip($attributes)), $attributes);
    }

    /**
     * Add decorator.
     *
     * @param string  $attribute
     * @param Closure $decorator
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
     * @param array $attributes
     *
     * @return array
     */
    protected function getAttributes(RoleInterface $role, array $attributes): array
    {
        $user = $this->server->getIdentity();
        if ($user === null || $attributes['_id'] != $user->getId() && !$user->isAdmin()) {
            return [];
        }

        return [
            'created' => function ($role, $requested) use ($attributes) {
                return $attributes['created']->toDateTime()->format('c');
            },
            'changed' => function ($role, $requested) use ($attributes) {
                return $attributes['changed']->toDateTime()->format('c');
            },
            'deleted' => function ($role, $requested) use ($attributes) {
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
     * @param array $attributes
     *
     * @return array
     */
    protected function getGroupAttributes(RoleInterface $role, array $attributes): array
    {
        if (!($role instanceof Group)) {
            return [];
        }

        return [
            'id' => (string) $attributes['_id'],
            'name' => $attributes['name'],
            'namespace' => $attributes['namespace'],
        ];
    }

    /**
     * Get user Attributes.
     *
     * @param RoleInterface
     * @param array $attributes
     *
     * @return array
     */
    protected function getUserAttributes(RoleInterface $role, array $attributes): array
    {
        if (!($role instanceof User)) {
            return [];
        }

        $user = $this->server->getIdentity();

        return [
            'id' => (string) $attributes['_id'],
            'username' => (string) $attributes['username'],
            'name' => (string) $attributes['username'],
            'namespace' => (string) $attributes['namespace'],
            'mail' => (string) $attributes['mail'],
            'avatar' => function ($role, $requested) use ($attributes) {
                if ($attributes['avatar'] instanceof Binary) {
                    return base64_encode($attributes['avatar']->getData());
                }

                return null;
            },
            'soft_quota' => function ($role, $requested) use ($attributes, $user) {
                if ($user === null) {
                    return null;
                }

                if ($attributes['_id'] == $user->getId() || $user->isAdmin()) {
                    return $attributes['soft_quota'];
                }

                return null;
            },
            'hard_quota' => function ($role, $requested) use ($attributes, $user) {
                if ($user === null) {
                    return null;
                }

                if ($attributes['_id'] == $user->getId() || $user->isAdmin()) {
                    return $attributes['hard_quota'];
                }

                return null;
            },
        ];
    }

    /**
     * Execute closures.
     *
     * @param RoleInterface
     * @param array $attributes
     * @param array $requested
     *
     * @return array
     */
    protected function translateAttributes(RoleInterface $role, array $attributes, array $requested): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value($role, $requested);

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
