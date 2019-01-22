<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Server\User;

class Message implements MessageInterface
{
    /**
     * Message type.
     *
     * @var string
     */
    protected $type;

    /**
     * Context.
     *
     * @var array
     */
    protected $context = [];

    /**
     * Template handler.
     *
     * @var TemplateHandler
     */
    protected $template;

    /**
     * Constructor.
     */
    public function __construct(string $type, TemplateHandler $template, array $context = [])
    {
        $this->type = $type;
        $this->template = $template;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(?User $user = null): string
    {
        $context = $this->context;
        $context['user'] = $user;

        return $this->template->getSubject($this->type, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(?User $user = null): string
    {
        $context = $this->context;
        $context['user'] = $user;

        return $this->template->getBody($this->type, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate(string $template, ?User $user = null): string
    {
        $context = $this->context;
        $context['user'] = $user;

        return $this->template->renderTemplate($this->type, $template, $context);
    }
}
