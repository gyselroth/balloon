<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Exception;

use Micro\Http\ExceptionInterface;

class InsufficientStorage extends \Sabre\DAV\Exception\InsufficientStorage implements ExceptionInterface
{
    //Error codes are not in order due backwards compatibility
    public const USER_QUOTA_FULL = 65;
    public const FILE_SIZE_LIMIT = 66;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 507;
    }
}
