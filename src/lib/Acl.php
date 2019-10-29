<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\AccessRole\Factory as AccessRoleFactory;
use Balloon\AccessRule\Factory as AccessRuleFactory;
use Balloon\Acl\Exception;
use Generator;
use Balloon\User\UserInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class Acl
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Access role factory.
     *
     * @var AccessRoleFactory
     */
    protected $role_factory;

    /**
     * Access rule factory.
     *
     * @var AccessRuleFactory
     */
    protected $rule_factory;

    /**
     * Initialize.
     */
    public function __construct(AccessRoleFactory $role_factory, AccessRuleFactory $rule_factory, LoggerInterface $logger)
    {
        $this->role_factory = $role_factory;
        $this->rule_factory = $rule_factory;
        $this->logger = $logger;
    }

    /**
     * Verify request.
     */
    public function isAllowed(ServerRequestInterface $request, UserInterface $user): bool
    {
        $this->logger->debug('verify access for identity ['.$user->getUsername().']', [
            'category' => get_class($this),
        ]);

        $roles = $this->role_factory->getAll([
            '$or' => [
                ['data.selectors' => $user->getUsername()],
                ['data.selectors' => '*'],
            ],
        ]);

        $names = [];
        foreach ($roles as $role) {
            $names[] = $role->getName();
        }

        $this->logger->debug('selected access-roles {access-roles}', [
            'category' => get_class($this),
            'access-roles' => $names,
        ]);

        if ($names === []) {
            $this->logger->info('no matching access roles for ['.$user->getUsername().']', [
                'category' => get_class($this),
            ]);

            throw new Exception\NotAllowed('not allowed to call this resource');
        }

        $rules = $this->rule_factory->getAll([
            'data.roles' => ['$in' => $names],
        ]);

        $method = $request->getMethod();
        $attributes = $request->getAttributes();

        $this->logger->debug('verify access for http request {method}:{uri} using {attributes}', [
            'category' => get_class($this),
            'uri' => $request->getUri(),
            'method' => $method,
            'attributes' => $attributes,
        ]);

        foreach ($rules as $rule) {
            $data = $rule->getData();

            $this->logger->debug('verify access rule ['.$rule->getName().']', [
                'category' => get_class($this),
            ]);

            if (empty(array_intersect($names, $data['roles'])) && !in_array('*', $data['roles'])) {
                continue;
            }

            if (!in_array($method, $data['verbs']) && !in_array('*', $data['verbs'])) {
                continue;
            }

            foreach ($data['selectors'] as $selector) {
                if ($selector === '*') {
                    return true;
                }

                if (isset($attributes[$selector]) && (in_array($attributes[$selector], $data['resources']) || in_array('*', $data['resources']))) {
                    return true;
                }
            }
        }

        $this->logger->info('access denied for user ['.$user->getUsername().'], no access rule match', [
            'category' => get_class($this),
            'roles' => $names,
        ]);

        throw new Exception\NotAllowed('not allowed to call this resource');
    }

    /**
     * Filter output resources.
     */
    public function filterOutput(ServerRequestInterface $request, UserInterface $user, iterable $resources)// : Generator
    {
        $count = 0;
        foreach ($resources as $resource) {
            ++$count;
            yield $resource;
        }

        if ($resources instanceof Generator) {
            return $resources->getReturn();
        }

        return $count;
    }
}
