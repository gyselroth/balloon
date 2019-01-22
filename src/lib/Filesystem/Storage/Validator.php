<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage;

use InvalidArgumentException;

class Validator
{
    /**
     * Validate options.
     */
    public static function validate(array $options): array
    {
        $adapter = null;
        if (isset($options['adapter'])) {
            $adapter = $options['adapter'];
        }

        if (!isset(Factory::ADAPTERS[$adapter])) {
            throw new InvalidArgumentException('valid storage adapter is required');
        }

        return $options;
    }
}
