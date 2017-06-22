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

class Conflict extends \Sabre\DAV\Exception\Conflict
{
    const ALREADY_THERE                      = 0x11;
    const CANT_BE_CHILD_OF_ITSELF            = 0x12;
    const NODE_WITH_SAME_NAME_ALREADY_EXISTS = 0x13;
    const SHARED_NODE_CANT_BE_CHILD_OF_SHARE = 0x14;
    const DELETED_PARENT                     = 0x15;
    const NODE_CONTAINS_SHARED_NODE          = 0x16;
    const PARENT_NOT_AVAILABLE_ANYMORE       = 0x17;
    const NOT_DELETED                        = 0x18;
    const READONLY                           = 0x19;
    const CANT_COPY_INTO_ITSELF              = 0x110;
    const NOT_SHARED                         = 0x111;
    const CAN_NOT_DELETE_OWN_ACCOUNT         = 0x112;
    const CHUNKS_LOST                        = 0x113;
    const CHUNKS_INVALID_SIZE                = 0x114;
    const INVALID_OFFSET                     = 0x115;
}
