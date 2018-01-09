<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Filesystem;
use Balloon\Server;
use TaskScheduler\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Scanner.
     *
     * @var Scanner
     */
    protected $scanner;

    /**
     * Constructor.
     *
     * @param App    $app
     * @param Server $server
     */
    public function __construct(Scanner $scanner, Server $server)
    {
        $this->scanner = $scanner;
        $this->fs = $server->getFilesystem();
    }

    /**
     * Run job.
     *
     * @return bool
     */
    public function start(): bool
    {
        $file = $this->fs->findNodeById($this->data['id']);
        $result = $this->scanner->scan($file);
        $infected = Scanner::FILE_INFECTED === $result;
        $this->scanner->handleFile($file, $infected);

        return true;
    }
}
