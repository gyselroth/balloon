<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Convert\AttributeDecorator as ConvertAttributeDecorator;
use Balloon\App\Convert\Converter;
use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Convert extends Controller
{
    /**
     * Converter.
     *
     * @var Converter
     */
    protected $converter;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Convert decorator.
     *
     * @var ConvertAttributeDecorator
     */
    protected $convert_decorator;

    /**
     * Constructor.
     */
    public function __construct(Converter $converter, Server $server, ConvertAttributeDecorator $convert_decorator)
    {
        $this->fs = $server->getFilesystem();
        $this->converter = $converter;
        $this->convert_decorator = $convert_decorator;
    }

    /**
     * Get supported formats.
     */
    public function getSupportedFormats(?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $result = $this->converter->getSupportedFormats($file);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get slaves.
     */
    public function getSlaves(?string $id = null, ?string $p = null, array $attributes = [], ?int $offset = 0, ?int $limit = 20): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $result = $this->converter->getSlaves($file, $offset, $limit, $total);
        $uri = '/api/v2/files/'.$file->getId().'/convert/slaves';
        $pager = new Pager($this->convert_decorator, $result, $attributes, $offset, $limit, $uri, $total);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Add new slave.
     */
    public function postSlaves(string $format, ?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $id = $this->converter->addSlave($file, $format);
        $result = $this->convert_decorator->decorate($this->converter->getSlave($id));

        return (new Response())->setCode(202)->setBody($result);
    }

    /**
     * Delete slave.
     */
    public function deleteSlaves(ObjectId $slave, ?string $id = null, ?string $p = null, bool $node = false): Response
    {
        $this->converter->deleteSlave($slave, $node);

        return (new Response())->setCode(204);
    }
}
