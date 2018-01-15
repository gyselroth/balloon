<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\User;

class Exception extends \Balloon\Server\Exception
{
    const HTTP_CODE = 400;

    const ALREADY_EXISTS = 100;
    const DOES_NOT_EXISTS = 101;
    const CAN_NOT_DELETE_OWN_ACCOUNT = 102;
    const MULTIPLE_USER_FOUND = 103;
}
