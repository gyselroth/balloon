<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

interface RoleInterface
{
    /**
     * Return role name as string.
     *
     * @return string
     */
    public function __toString(): string;

    /**
     * Get attributes.
     *
     * @return array
     */
    public function getAttributes(): array;
}
