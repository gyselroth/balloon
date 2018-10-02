<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter\Smb;

use InvalidArgumentException;

class Validator
{
    /**
     * Validate options.
     */
    public static function validate(array $options): array
    {
        if (!isset($options['host'])) {
            throw new InvalidArgumentException('host is required for smb');
        }

        if (!isset($options['share'])) {
            throw new InvalidArgumentException('share name is required for smb');
        }

        return $options;
    }
}
