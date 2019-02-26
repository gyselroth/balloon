<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Preview\Preview as PreviewGetter;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;

class Preview extends Controller
{
    /**
     * Preview.
     *
     * @var PreviewGetter
     */
    protected $preview;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     */
    public function __construct(PreviewGetter $preview, Server $server)
    {
        $this->fs = $server->getFilesystem();
        $this->preview = $preview;
    }

    /**
     * Get Preview.
     */
    public function get(?string $id = null, ?string $p = null, ?string $encode = null): Response
    {
        $node = $this->fs->getNode($id, $p, File::class);
        $data = $this->preview->getPreview($node);
        $response = (new Response())
            ->setOutputFormat('text')
            ->setBody($data, true)
            ->setHeader('Content-Type', 'image/png');

        return $response;
    }
}
