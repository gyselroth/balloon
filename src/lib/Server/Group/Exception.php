<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\Group;

class Exception extends \Balloon\Server\Exception
{
    const HTTP_CODE = 400;

    const ALREADY_EXISTS = 102;
    const DOES_NOT_EXISTS = 103;
    const UNIQUE_MEMBER = 103;
}
