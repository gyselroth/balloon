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
    const ALREADY_THERE                      = 17;
    const CANT_BE_CHILD_OF_ITSELF            = 18;
    const NODE_WITH_SAME_NAME_ALREADY_EXISTS = 19;
    const SHARED_NODE_CANT_BE_CHILD_OF_SHARE = 20;
    const DELETED_PARENT                     = 21;
    const NODE_CONTAINS_SHARED_NODE          = 22;
    const PARENT_NOT_AVAILABLE_ANYMORE       = 23;
    const NOT_DELETED                        = 24;
    const READONLY                           = 25;
    const CANT_COPY_INTO_ITSELF              = 272;
    const NOT_SHARED                         = 273;
    const CAN_NOT_DELETE_OWN_ACCOUNT         = 274;
    const CHUNKS_LOST                        = 275;
    const CHUNKS_INVALID_SIZE                = 276;
    const INVALID_OFFSET                     = 278;
}
