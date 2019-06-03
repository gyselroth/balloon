<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\User\Exception;

use Micro\Http\ExceptionInterface;

class InvalidArgument extends \InvalidArgumentException implements ExceptionInterface
{
    const INVALID_USERNAME = 1;
    const INVALID_PASSWORD = 2;
    const INVALID_QUOTA = 3;
    const INVALID_AVATAR = 4;
    const INVALID_MAIL = 5;
    const INVALID_NAMESPACE = 6;
    const INVALID_OPTIONAL = 7;
    const INVALID_ATTRIBUTE = 8;
    const IDENTIFIER_NOT_UNIQUE = 9;
    const CAN_NOT_DELETE_OWN_ACCOUNT = 10;
    const INVALID_LOCALE = 11;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 422;
    }
}
