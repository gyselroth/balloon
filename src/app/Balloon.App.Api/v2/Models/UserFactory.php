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
use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Server;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

class UserFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Hook.
     *
     * @var Hook
     */
    //protected $hook;


    /**
     * Get user Attributes.
     */
    protected function getAttributes(ResourceInterface $role, ServerRequestInterface $request): array
    {
        $attributes = $role->toArray();
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
            'has_password' => function ($role) use ($user, $attributes) {
                if ($attributes['_id'] == $user->getId() || $user->isAdmin()) {
                    return $role->hasPassword();
                }

                return null;
            },
            'auth' => function () use ($user) {
                $identity = $user->getIdentity();
                if ($identity === null) {
                    return null;
                }

                if ($identity->getAdapter() instanceof InternalAuthInterface) {
                    return $identity->getAdapter()->isInternal() ? 'internal' : 'external';
                }

                return 'external';
            },
        ];

        return $result;
    }
}

