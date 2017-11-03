<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Async\AbstractJob;
use Balloon\Server;
use Balloon\App\ClamAv\App\Cli as App;

class Job extends AbstractJob
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Constructor.
     *
     * @param App    $app
     * @param Server $server
     */
    public function __construct(App $app, Server $server)
    {
        $this->app = $app;
        $this->fs = $server->getFilesystem();
    }

    /**
     * Run job.
     *
     * @return bool
     */
    public function start(): bool
    {
        $file = $this->fs->findNodeWithId($this->data['id']);
        $result = $this->app->scan($file);
        $infected = Cli::FILE_INFECTED === $result;
        $this->app->handleFile($file, $infected);

        return true;
    }
}
