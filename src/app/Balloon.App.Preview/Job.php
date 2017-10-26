<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use Balloon\Async\AbstractJob;
use Balloon\Server;

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
     * Start job.
     *
     * @return bool
     */
    public function start(): bool
    {
        $file = $this->fs->findNodeWithId($this->data['id']);
        $this->app->createPreview($file);

        return true;
    }
}
