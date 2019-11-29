<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

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
     * Preview.
     *
     * @var Preview
     */
    protected $preview;

    /**
     * Constructor.
     */
    public function __construct(Preview $preview, Server $server)
    {
        $this->preview = $preview;
        $this->fs = $server->getFilesystem();
    }

    /**
     * Start job.
     */
    public function start(): bool
    {
        $file = $this->fs->findNodeById($this->data['id']);
        $this->preview->createPreview($file);

        return true;
    }
}
