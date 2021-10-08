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
    const NODE_NOT_FOUND = 49;
    const SHARE_NOT_FOUND = 50;
    const REFERENCE_NOT_FOUND = 51;
    const NOT_ALL_NODES_FOUND = 52;
    const DESTINATION_NOT_FOUND = 54;
    const PARENT_NOT_FOUND = 55;
    const CONTENTS_NOT_FOUND = 57;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 404;
    }
}
