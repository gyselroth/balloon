<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Acl\Exception;

class Forbidden extends \Sabre\DAV\Exception\Forbidden
{
    const NOT_ALLOWED_TO_RESTORE = 0x21;
    const NOT_ALLOWED_TO_DELETE = 0x22;
    const NOT_ALLOWED_TO_MODIFY = 0x23;
    const NOT_ALLOWED_TO_OVERWRITE = 0x24;
    const NOT_ALLOWED_TO_SHARE = 0x25;
    const NOT_ALLOWED_TO_CREATE = 0x26;
    const NOT_ALLOWED_TO_MOVE = 0x27;
    const NOT_ALLOWED_TO_ACCESS = 0x28;
    const ADMIN_PRIV_REQUIRED = 0x29;
    const NOT_ALLOWED_TO_UNDELETE = 0x210;
}
