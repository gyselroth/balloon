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

class InsufficientStorage extends \Sabre\DAV\Exception\InsufficientStorage
{
    const HTTP_CODE = 507;

    const USER_QUOTA_FULL = 65;
    const FILE_SIZE_LIMIT = 66;
}
