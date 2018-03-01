<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\DesktopClient\Exception;

use Micro\Http\ExceptionInterface;

class GithubRequestFailed extends \Balloon\Exception implements ExceptionInterface
{
    /**
     * Status code.
     *
     * @var int
     */
    protected $status_code = 500;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->code;
    }

    /**
     * Set status code.
     *
     * @param int $code
     */
    public function setStatusCode(int $code): int
    {
        $this->status_code = $code;
    }
}
