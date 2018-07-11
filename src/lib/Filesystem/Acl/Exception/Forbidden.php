<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Acl\Exception;

use Micro\Http\ExceptionInterface;

class Forbidden extends \Sabre\DAV\Exception\Forbidden implements ExceptionInterface
{
    const NOT_ALLOWED_TO_RESTORE = 33;
    const NOT_ALLOWED_TO_DELETE = 34;
    const NOT_ALLOWED_TO_MODIFY = 35;
    const NOT_ALLOWED_TO_OVERWRITE = 36;
    const NOT_ALLOWED_TO_MANAGE = 37;
    const NOT_ALLOWED_TO_CREATE = 38;
    const NOT_ALLOWED_TO_MOVE = 39;
    const NOT_ALLOWED_TO_ACCESS = 40;
    const ADMIN_PRIV_REQUIRED = 41;
    const NOT_ALLOWED_TO_UNDELETE = 42;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 403;
    }
}
