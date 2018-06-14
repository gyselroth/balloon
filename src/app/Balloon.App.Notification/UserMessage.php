<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

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
    protected $body;

    /**
     * Role Attribute decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $template;

    /**
     * Constructor.
     *
     * @param string                 $subject
     * @param string                 $message
     * @param RoleAttributeDecorator $role_decorator
     */
    public function __construct(string $subject, string $body, TemplateHandler $template)
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->template = $template;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(User $user): string
    {
        return $this->template->getSubject('user_message', [
            'user' => $user,
            'user_subject' => $this->subject,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(User $user): string
    {
        return $this->template->getBody('user_message', [
            'user' => $user,
            'user_body' => $this->body,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate(string $template, User $user): string
    {
        return $this->template->renderTemplate('user_message', $template, [
            'user' => $user,
            'user_body' => $this->body,
        ]);
    }
}
