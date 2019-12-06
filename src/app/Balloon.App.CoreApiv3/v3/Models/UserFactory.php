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
use Balloon\User\Factory as UserResourceFactory;
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

    public function __construct(UserResourceFactory $user_factory)
    {
        $this->user_factory = $user_factory;
    }


    /**
     * Get user Attributes.
     */
    protected function getAttributes(ResourceInterface $user, ServerRequestInterface $request): array
    {
        $attributes = $user->toArray();
        $quota = null;
        $user_factory = $this->user_factory;

        $result = [
            'id' => (string) $attributes['_id'],
            'username' => (string) $attributes['username'],
            'name' => (string) $attributes['username'],
            //'admin' => (bool) $attributes['admin'],
            'namespace' => isset($attributes['namespace']) ? (string) $attributes['namespace'] : null,
            'mail' => function ($user) use ($attributes) {
                if (!isset($attributes['mail'])) {
                    return null;
                }

                if ($attributes['_id'] == $user->getId() /*|| $user->isAdmin()*/) {
                    return (string) $attributes['mail'];
                }

                return null;
            },
            'locale' => isset($attributes['locale']) ? (string) $attributes['locale'] : null,
            'hard_quota' => isset($attributes['hard_quota']) ? (int) $attributes['hard_quota'] : null,
            'soft_quota' => isset($attributes['soft_quota']) ? (int) $attributes['soft_quota'] : null,
            'available' => function ($user) use (&$quota, $user_factory, $attributes/*, $user*/) {
                $quota === null ? $quota = $user_factory->getQuotaUsage($user) : null;
                if ($attributes['_id'] == $user->getId()/* || $user->isAdmin()*/) {
                    return $quota['available'];
                }

                return null;
            },
            'used' => function ($user) use (&$quota, $user_factory, $attributes/*, $user*/) {
                $quota === null ? $quota = $user_factory->getQuotaUsage($user) : null;
                if ($attributes['_id'] == $user->getId()/* || $user->isAdmin()*/) {
                    return $quota['used'];
                }

                return null;
            },
            'has_password' => function ($user) use (/*$user,*/ $attributes) {
                if ($attributes['_id'] == $user->getId() /*|| $user->isAdmin()*/) {
                    return $user->hasPassword();
                }

                return null;
            },
            /*'auth' => function () use ($user) {
                $identity = $user->getIdentity();
                if ($identity === null) {
                    return null;
                }

                if ($identity->getAdapter() instanceof InternalAuthInterface) {
                    return $identity->getAdapter()->isInternal() ? 'internal' : 'external';
                }

                return 'external';
            },*/
        ];

        return $result;
    }
}

