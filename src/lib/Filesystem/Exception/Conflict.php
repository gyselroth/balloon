<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Exception;

use Micro\Http\ExceptionInterface;

class Conflict extends \Sabre\DAV\Exception\Conflict implements ExceptionInterface
{
    //Error codes are not in order due backwards compatibility
    public const ALREADY_THERE = 17;
    public const CANT_BE_CHILD_OF_ITSELF = 18;
    public const NODE_WITH_SAME_NAME_ALREADY_EXISTS = 19;
    public const SHARED_NODE_CANT_BE_CHILD_OF_SHARE = 20;
    public const DELETED_PARENT = 21;
    public const NODE_CONTAINS_SHARED_NODE = 22;
    public const PARENT_NOT_AVAILABLE_ANYMORE = 23;
    public const READONLY = 25;
    public const CANT_COPY_INTO_ITSELF = 272;
    public const NOT_SHARED = 273;
    public const CHUNKS_LOST = 275;
    public const CHUNKS_INVALID_SIZE = 276;
    public const INVALID_OFFSET = 278;
    public const SHARED_NODE_CANT_BE_INDIRECT_CHILD_OF_SHARE = 279;
    public const DYNAMIC_PARENT = 280;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 422;
    }
}
