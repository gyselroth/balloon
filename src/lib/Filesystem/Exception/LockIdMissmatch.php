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

class LockIdMissmatch extends \Balloon\Exception implements ExceptionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 422;
    }
}
