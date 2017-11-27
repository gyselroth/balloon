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

use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;
use TaskScheduler\Async;

class Hook extends AbstractHook
{
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
    public function __construct(Async $async)
    {
        $this->async = $async;
    }

    /**
     * Run: postPutFile.
     *
     * Executed pre a put file request
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
}
