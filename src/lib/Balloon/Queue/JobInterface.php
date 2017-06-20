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
use Balloon\Filesystem;
use \MongoDB\Database;

interface JobInterface
{
    /**
     * Run job
     *
     * @param   Filesystem $fs
     * @return  bool
     */
    public function getData(): array;


    /**
     * Run job
     *
     * @param   Filesystem $fs
     * @return  bool
     */
    public function run(Filesystem $fs, Logger $logger, Config $config): bool;
}
