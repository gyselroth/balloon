<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Api\v2;

use Balloon\App\Preview\Preview as PreviewGetter;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;

class Preview
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
     * Node attribute decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Constructor.
     */
    public function __construct(PreviewGetter $preview, Server $server, NodeAttributeDecorator $decorator)
    {
        $this->fs = $server->getFilesystem();
        $this->preview = $preview;
        $this->node_decorator = $decorator;
    }

    /**
     * Update Preview.
     */
    public function patch(string $id): Response
    {
        $node = $this->fs->getNode($id, File::class);
        $data = $this->preview->setPreview($node, fopen('php://input', 'r'));

        return (new Response())->setBody(
            $this->node_decorator->decorate($node)
        )->setCode(200);
    }

    /**
     * Delete Preview.
     */
    public function delete(string $id): Response
    {
        $node = $this->fs->getNode($id, File::class);
        $this->preview->getPreview($node);
        $data = $this->preview->deletePreview($node);

        return (new Response())->setCode(204);
    }

    /**
     * Get Preview.
     */
    public function get(string $id, ?string $encode = null): Response
    {
        $node = $this->fs->getNode($id, File::class);
        $data = $this->preview->getPreview($node);
        $response = (new Response())
            ->setOutputFormat('text')
            ->setBody($data, true)
            ->setHeader('Content-Type', 'image/png');

        return $response;
    }
}
