<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\Exception;

class NotAuthenticated extends \Sabre\DAV\Exception\NotAuthenticated
{
    const HTTP_CODE = 403;

    const NOT_AUTHENTICATED = 81;
    const USER_DELETED = 82;
    const USER_DOES_NOT_EXISTS = 83;
}
