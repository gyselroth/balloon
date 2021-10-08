<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\Api\v2;

use Balloon\App\Convert\AttributeDecorator as ConvertAttributeDecorator;
use Balloon\App\Convert\Converter;
use Balloon\AttributeDecorator\Pager;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use function MongoDB\BSON\toPHP;

class Convert
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
    public function getSupportedFormats(string $id): Response
    {
        $file = $this->fs->getNode($id, File::class);
        $result = $this->converter->getSupportedFormats($file);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get slaves.
     *
     * @param null|mixed $query
     */
    public function getSlaves(string $id, $query = null, array $attributes = [], ?int $offset = 0, ?int $limit = 20): Response
    {
        $file = $this->fs->getNode($id, File::class);
        $result = $this->converter->getSlaves($file, $this->parseQuery($query), $offset, $limit, $total);
        $uri = '/api/v2/files/'.$file->getId().'/convert/slaves';
        $pager = new Pager($this->convert_decorator, $result, $attributes, $offset, $limit, $uri, $total);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Add new slave.
     */
    public function postSlaves(string $format, string $id): Response
    {
        $file = $this->fs->getNode($id);
        $id = $this->converter->addSlave($file, $format);
        $result = $this->convert_decorator->decorate($this->converter->getSlave($id));

        return (new Response())->setCode(202)->setBody($result);
    }

    /**
     * Delete slave.
     */
    public function deleteSlaves(ObjectId $slave, string $id, bool $node = false): Response
    {
        $this->converter->deleteSlave($slave, $node);

        return (new Response())->setCode(204);
    }

    /**
     * Parse query.
     */
    protected function parseQuery($query): array
    {
        if ($query === null) {
            $query = [];
        } elseif (is_string($query)) {
            $query = toPHP(fromJSON($query), [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);
        }

        return (array) $query;
    }
}
