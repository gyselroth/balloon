<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Queue;

use \Psr\Log\LoggerInterface as Logger;
use Balloon\Config;
use \MongoDB\Database;

abstract class AbstractJob implements JobInterface
{
    /**
     * Data
     *
     * @var array
     **/
    protected $data = [];


    /**
     * Create thumbnail job
     *
     * @param  array $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
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
