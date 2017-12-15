<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;

class UserMessage implements MessageInterface
{
    /**
     * User.
     *
     * @var User
     */
    protected $user;


    /**
     * Constructor.
     *
     * @param Database       $db
     * @param Server         $server
     * @param LoggerInterace $logger
     * @param iterable       $config
     */
    public function __construct(string $subject, string $message, RoleAttributeDecorator $role_decorator)
    {
        $this->subject = $subject;
        $this->message = $message;
        $this->role_decorator = $role_decorator;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubject(User $user): string
    {
        $role_decorator = $this->role_decorator;

        $string = preg_replace_callback('/(\{user\.(([a-z]\.*)+)\})/', function ($match) use($user, $role_decorator) {
            return $role_decorator->decorate($user, [$match[2]])[$match[2]];
        }, $this->subject);
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(User $user): string
    {

    }
}
