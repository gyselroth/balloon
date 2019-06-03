<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server\User\Exception;

use Micro\Http\ExceptionInterface;

class NotAuthenticated extends \Sabre\DAV\Exception\NotAuthenticated implements ExceptionInterface
{
    const USER_DELETED = 1;
    const USER_NOT_FOUND = 2;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 403;
    }
}
