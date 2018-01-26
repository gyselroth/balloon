<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;

class NodeMessage implements MessageInterface
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
     * Node.
     *
     * @var NodeInterface
     */
    protected $node;

    /**
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Role Attribute decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    protected $template;

    /**
     * Constructor.
     *
     * @param string                 $subject
     * @param string                 $message
     * @param TemplateHandler        $template
     * @param NodeInterface          $node
     * @param AttributeDecorator     $decorator
     * @param RoleAttributeDecorator $role_decorator
     */
    public function __construct(string $type, TemplateHandler $template, NodeInterface $node)
    {
        $this->type = $type;
        $this->template = $template;
        $this->node = $node;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(User $user): string
    {
        return $this->template->parseSubjectTemplate($this->type, $user, $this->node);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(User $user): string
    {
        return $this->template->parseBodyTemplate($this->type, $user, $this->node);
    }

    public function getMailBody(User $user): string
    {
        return $this->template->parseMailBodyTemplate($this->type, $user, $this->node);
    }
}
