<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Async;
use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;

class Hook extends AbstractHook
{
    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param App   $app
     * @param Async $async
     */
    public function __construct(App $app, Async $async)
    {
        $this->app = $app;
        $this->async = $async;
    }

    /**
     * Run: postPutFile.
     *
     * Executed post a put file request
     *
     * @param File            $node
     * @param resource|string $content
     * @param bool            $force
     * @param array           $attributes
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
        $this->addJob($node);
    }

    /**
     * Run: postRestoreFile.
     *
     * Executed post version rollback
     *
     * @param File $node
     * @param int  $version
     */
    public function postRestoreFile(File $node, int $version): void
    {
        $this->addJob($node);
    }

    /**
     * Add job.
     *
     * @param File $node
     */
    protected function addJob(File $node): void
    {
        $shadow = $node->getAppAttribute($this->app, 'shadow');
        if (null === $shadow) {
            return;
        }

        foreach ($shadow as $format) {
            $this->async->addJob(Job::class, [
                'id' => $node->getId(),
                'format' => $format,
            ]);
        }
    }
}
