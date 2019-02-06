<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Exception;

use Micro\Http\ExceptionInterface;

class Conflict extends \Sabre\DAV\Exception\Conflict implements ExceptionInterface
{
    //Error codes are not in order due backwards compatibility
    const ALREADY_THERE = 17;
    const CANT_BE_CHILD_OF_ITSELF = 18;
    const NODE_WITH_SAME_NAME_ALREADY_EXISTS = 19;
    const SHARED_NODE_CANT_BE_CHILD_OF_SHARE = 20;
    const DELETED_PARENT = 21;
    const NODE_CONTAINS_SHARED_NODE = 22;
    const PARENT_NOT_AVAILABLE_ANYMORE = 23;
    const READONLY = 25;
    const CANT_COPY_INTO_ITSELF = 272;
    const NOT_SHARED = 273;
    const CHUNKS_LOST = 275;
    const CHUNKS_INVALID_SIZE = 276;
    const INVALID_OFFSET = 278;
    const SHARED_NODE_CANT_BE_INDIRECT_CHILD_OF_SHARE = 279;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 422;
    }
}
