<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

interface JobInterface
{
    /**
     * Get job data.
     *
     * @param array $data
     *
     * @return JobInterface
     */
    public function setData(array $data): JobInterface;

    /**
     * Get job data.
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Start job.
     *
     * @return bool
     */
    public function start(): bool;
}
