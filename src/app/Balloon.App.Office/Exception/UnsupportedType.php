<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Exception;

use Micro\Http\ExceptionInterface;

class UnsupportedType extends \Balloon\Exception implements ExceptionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return 400;
    }
}
