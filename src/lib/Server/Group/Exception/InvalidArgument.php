<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\Group\Exception;

use Micro\Http\ExceptionInterface;

class InvalidArgument extends \InvalidArgumentException implements ExceptionInterface
{
    public const INVALID_NAME = 1;
    public const INVALID_NAMESPACE = 2;
    public const INVALID_MEMBER = 3;
    public const INVALID_OPTIONAL = 4;
    public const INVALID_ATTRIBUTE = 5;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 422;
    }
}
