<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Exception;

class NotAuthenticated extends \Sabre\DAV\Exception\NotAuthenticated
{
    const NOT_AUTHENTICATED = 0x51;
    const USER_DELETED      = 0x52;
}
