<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

abstract class AbstractJob implements JobInterface
{
    /**
     * Data
     *
     * @var array
     **/
    protected $data = [];


    /**
     * Get data
     *
     * @param  array $data
     * @return JobInterface
     */
    public function setData(array $data): JobInterface
    {
        $this->data = $data;
        return $this;
    }


    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
