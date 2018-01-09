<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Exception;

class NotFound extends \Sabre\DAV\Exception\NotFound
{
    const HTTP_CODE = 404;

    const NODE_NOT_FOUND = 49;
    const SHARE_NOT_FOUND = 50;
    const REFERENCE_NOT_FOUND = 51;
    const NOT_ALL_NODES_FOUND = 52;
    const USER_NOT_FOUND = 53;
    const DESTINATION_NOT_FOUND = 54;
    const PARENT_NOT_FOUND = 55;
    const PREVIEW_NOT_FOUND = 56;
    const CONTENTS_NOT_FOUND = 57;
}
