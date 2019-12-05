<?php

declare(strict_types=1);

/**
 * tubee
 *
 * @copyright   Copryright (c) 2017-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Process\Exception;

use Balloon\Rest\Exception\ExceptionInterface;

class NotFound extends \Balloon\Exception implements ExceptionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 404;
    }
}
