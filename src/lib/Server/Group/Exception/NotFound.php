<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\Group\Exception;

use Micro\Http\ExceptionInterface;

class NotFound extends Balloon\Exception implements ExceptionInterface
{
    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return 404;
    }
}
