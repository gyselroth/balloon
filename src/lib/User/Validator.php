<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\User;

use InvalidArgumentException;

class Validator
{
    /**
     * Validate resource.
     */
    public static function validatePolicy(array $resource, string $policy): array
    {
        if (isset($resource['data']['password']) && !preg_match($policy, $resource['data']['password'])) {
            throw new InvalidArgumentException('password does not match password policy '.$policy);
        }

        return $resource;
    }
}
