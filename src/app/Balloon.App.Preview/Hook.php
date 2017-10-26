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

use Balloon\Async;
use Balloon\Exception;
use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;
use MongoDB\GridFS\Exception\FileNotFoundException;

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
     * Run: preDeleteFile.
     *
     * Executed pre a file gets deleted
     *
     * @param File   $node
     * @param bool   $force
     * @param string $recursion
     * @param bool   $recursion_first
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (true === $force) {
            try {
                $this->app->deletePreview($node);
            } catch (FileNotFoundException $e) {
                $this->logger->debug('could not remove preview from file ['.$node->getId().'], preview does not exists', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }
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
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
        ]);
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
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
        ]);
    }
}
