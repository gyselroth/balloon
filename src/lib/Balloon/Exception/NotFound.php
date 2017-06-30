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

class NotFound extends \Sabre\DAV\Exception\NotFound
{
    const NODE_NOT_FOUND        = 0x31;
    const SHARE_NOT_FOUND       = 0x32;
    const REFERENCE_NOT_FOUND   = 0x33;
    const NOT_ALL_NODES_FOUND   = 0x34;
    const USER_NOT_FOUND        = 0x35;
    const DESTINATION_NOT_FOUND = 0x36;
    const PARENT_NOT_FOUND      = 0x37;
    const PREVIEW_NOT_FOUND     = 0x38;
    const CONTENTS_NOT_FOUND    = 0x39;
}
