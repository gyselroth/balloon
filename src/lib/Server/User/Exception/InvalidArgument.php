<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\User\Exception;

use Micro\Http\ExceptionInterface;

class InvalidArgument extends \InvalidArgumentException implements ExceptionInterface
{
    public const INVALID_USERNAME = 1;
    public const INVALID_PASSWORD = 2;
    public const INVALID_QUOTA = 3;
    public const INVALID_AVATAR = 4;
    public const INVALID_MAIL = 5;
    public const INVALID_NAMESPACE = 6;
    public const INVALID_OPTIONAL = 7;
    public const INVALID_ATTRIBUTE = 8;
    public const IDENTIFIER_NOT_UNIQUE = 9;
    public const CAN_NOT_DELETE_OWN_ACCOUNT = 10;
    public const INVALID_LOCALE = 11;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 422;
    }
}
