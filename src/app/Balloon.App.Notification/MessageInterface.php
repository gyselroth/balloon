<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Server\User;

interface MessageInterface
{
    /**
     * Get subject.
     */
    public function getSubject(?User $user = null): string;

    /**
     * Get body.
     */
    public function getBody(?User $user = null): string;

    /**
     * Render template.
     */
    public function renderTemplate(string $template, ?User $user = null): string;

    /**
     * Get type.
     */
    public function getType(): string;

    /**
     * Get context.
     */
    public function getContext(): array;
}
