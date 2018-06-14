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

interface MessageInterface
{
    /**
     * Get subject.
     *
     * @param User $user
     *
     * @return string
     */
    public function getSubject(User $user): string;

    /**
     * Get body.
     *
     * @param User $user
     *
     * @return string
     */
    public function getBody(User $user): string;

    /**
     * Render template.
     */
    public function renderTemplate(string $template, User $user): string;
}
