<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;

class UserMessage implements MessageInterface
{
    /**
     * Subject.
     *
     * @var string
     */
    protected $subject;

    /**
     * Message.
     *
     * @var string
     */
    protected $message;

    /**
     * Role Attribute decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    /**
     * Constructor.
     *
     * @param string                 $subject
     * @param string                 $message
     * @param RoleAttributeDecorator $role_decorator
     */
    public function __construct(string $subject, string $message, RoleAttributeDecorator $role_decorator)
    {
        $this->subject = $subject;
        $this->message = $message;
        $this->role_decorator = $role_decorator;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(User $user): string
    {
        return $this->decorate('subject', $user);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(User $user): string
    {
        return $this->decorate('message', $user);
    }

    /**
     * Replace variables.
     *
     * @param string $type
     * @param User   $user
     */
    protected function decorate(string $type, User $user): string
    {
        $role_decorator = $this->role_decorator;

        $string = preg_replace_callback('/(\{user\.(([a-z]\.*)+)\})/', function ($match) use ($user, $role_decorator) {
            return $role_decorator->decorate($user, [$match[2]])[$match[2]];
        }, $this->{$type});

        return $string;
    }
}
