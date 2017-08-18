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
    const NODE_NOT_FOUND        = 49;
    const SHARE_NOT_FOUND       = 50;
    const REFERENCE_NOT_FOUND   = 51;
    const NOT_ALL_NODES_FOUND   = 52;
    const USER_NOT_FOUND        = 53;
    const DESTINATION_NOT_FOUND = 54;
    const PARENT_NOT_FOUND      = 55;
    const PREVIEW_NOT_FOUND     = 56;
    const CONTENTS_NOT_FOUND    = 57;
}
