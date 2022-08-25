<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Acl\Exception;

use Micro\Http\ExceptionInterface;

class Forbidden extends \Sabre\DAV\Exception\Forbidden implements ExceptionInterface
{
    public const NOT_ALLOWED_TO_RESTORE = 33;
    public const NOT_ALLOWED_TO_DELETE = 34;
    public const NOT_ALLOWED_TO_MODIFY = 35;
    public const NOT_ALLOWED_TO_OVERWRITE = 36;
    public const NOT_ALLOWED_TO_MANAGE = 37;
    public const NOT_ALLOWED_TO_CREATE = 38;
    public const NOT_ALLOWED_TO_MOVE = 39;
    public const NOT_ALLOWED_TO_ACCESS = 40;
    public const ADMIN_PRIV_REQUIRED = 41;
    public const NOT_ALLOWED_TO_UNDELETE = 42;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 403;
    }
}
