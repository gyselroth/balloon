<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\Helper;
use Balloon\Server;
use Closure;
use MongoDB\BSON\Binary;

class AttributeDecorator
{
    /**
     * User.
     *
     * @var User
     */
    protected $user;

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
        $this->user = $server->getIdentity();
    }

    /**
     * Decorate attributes.
     *
     * @param RoleInterface $role
     * @param array         $attributes
     *
     * @return array
     */
    public function decorate(RoleInterface $role, ?array $attributes): array
    {
        if (null === $attributes) {
            $attributes = [];
        }

        $role_attributes = $role->getAttributes();

        $attrs = array_merge(
            $this->getAttributes($role, $role_attributes),
            $this->getUserAttributes($role, $role_attributes),
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
        return [
            'id' => (string) $attributes['id'],
            'mail' => (string) $attributes['mail'],
            'namespace' => (string) $attributes['namespace'],
            'avatar' => function ($role, $requested) use ($attributes) {
                if ($attributes['avatar'] instanceof Binary) {
                    return base64_encode($attributes['avatar']);
                }

                return null;
            },
            'created' => function ($role, $requested) use ($attributes) {
                return Helper::DateTimeToUnix($attributes['created']);
            },
            'changed' => function ($role, $requested) use ($attributes) {
                return Helper::DateTimeToUnix($attributes['changed']);
            },
            'deleted' => function ($role, $requested) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return false;
                }

                return Helper::DateTimeToUnix($attributes['deleted']);
            },
        ];
    }

    /**
     * Get Attributes.
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

        $user = $this->user;

        return [
            'name' => (string) $attributes['username'],
            'soft_quota' => function ($role, $requested) use ($attributes, $user) {
                if ($user === null) {
                    return null;
                }

                if ($attributes['id'] == $user->getId() || $user->isAdmin()) {
                    return $attributes['soft_quota'];
                }

                return null;
            },
            'hard_quota' => function ($role, $requested) use ($attributes, $user) {
                if ($user === null) {
                    return null;
                }

                if ($attributes['id'] == $user->getId() || $user->isAdmin()) {
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
            }
        }

        return $attributes;
    }
}
