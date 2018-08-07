<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Icewind\SMB\IShare;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Smb implements AdapterInterface
{
    /**
     * SMB share.
     *
     * @var IShare
     */
    protected $share;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * SMB storage.
     *
     * @param Database
     */
    public function __construct(IShare $share, LoggerInterface $logger)
    {
        $this->share = $share;
        $this->logger = $logger;
    }

    /**
     * Get SMB share.
     */
    public function getShare(): IShare
    {
        return $this->share;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node, array $attributes): bool
    {
        $this->verifyAttributes($attributes);
        var_dump($this->share->stat($attributes['path']));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, array $attributes): bool
    {
        $this->verifyAttributes($attributes);
        $this->share->del($attributes['path']);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file, array $attributes)
    {
        $this->verifyAttributes($attributes);

        return $this->share->read($attributes['path']);
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, $contents): array
    {
        $path = $file->getPath();

        return [
            'path' => $path,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name, array $attributes): array
    {
        if (count($attributes) === 0) {
            $path = DIRECTORY_SEPARATOR.$name;
        } else {
            $this->verifyAttributes($attributes);
            $path = $attributes['path'].DIRECTORY_SEPARATOR.$name;
        }

        $this->share->mkdir($path);

        return [
            'path' => $path,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCollection(Collection $collection, array $attributes): bool
    {
        $this->verifyAttributes($attributes);
        $path = $collection->getPath();
        $this->share->rmdir($path);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name, array $attributes): bool
    {
    }

    /**
     * Verify SMB reference.
     */
    protected function verifyAttributes(array $attributes): bool
    {
        if (!isset($attributes['path'])) {
            throw Exception('no path given for smb storage definiton');
        }

        return true;
    }
}
