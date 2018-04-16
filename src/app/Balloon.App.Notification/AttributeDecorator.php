<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttribueDecorator;
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
     * Role decorator.
     *
     * @var RoleAttribueDecorator
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
     *
     * @param Server                $server
     * @param RoleAttribueDecorator $role_decorator
     */
    public function __construct(Server $server, RoleAttribueDecorator $role_decorator)
    {
        $this->server = $server;
        $this->role_decorator = $role_decorator;
    }

    /**
     * Decorate attributes.
     *
     * @param array $message
     * @param array $attributes
     *
     * @return array
     */
    public function decorate(array $message, array $attributes = []): array
    {
        $requested = $attributes;
        $attrs = $this->getAttributes($message);

        if (0 === count($requested)) {
            return $this->translateAttributes($message, $attrs);
        }

        return $this->translateAttributes($message, array_intersect_key($attrs, array_flip($requested)));
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
     * @param array $message
     *
     * @return array
     */
    protected function getAttributes(array $message): array
    {
        $server = $this->server;
        $role_decorator = $this->role_decorator;

        return [
            'id' => (string) $message['_id'],
            'message' => (string) $message['body'],
            'subject' => (string) $message['subject'],
            'sender' => function ($message) use ($server, $role_decorator) {
                if (!isset($message['sender'])) {
                    return null;
                }

                try {
                    return $role_decorator->decorate($server->getUserById($message['sender']), ['_links', 'id', 'username']);
                } catch (\Exception $e) {
                    return null;
                }
            },
        ];
    }

    /**
     * Execute closures.
     *
     * @param array $message
     * @param array $attributes
     *
     * @return array
     */
    protected function translateAttributes(array $message, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value->call($this, $message);

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
