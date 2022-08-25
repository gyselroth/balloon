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

class NotFound extends \Sabre\DAV\Exception\NotFound implements ExceptionInterface
{
    //Error codes are not in order due backwards compatibility
    public const NODE_NOT_FOUND = 49;
    public const SHARE_NOT_FOUND = 50;
    public const REFERENCE_NOT_FOUND = 51;
    public const NOT_ALL_NODES_FOUND = 52;
    public const DESTINATION_NOT_FOUND = 54;
    public const PARENT_NOT_FOUND = 55;
    public const CONTENTS_NOT_FOUND = 57;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 404;
    }
}
