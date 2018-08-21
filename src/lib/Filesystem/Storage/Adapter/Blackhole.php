<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Exception;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;

class Blackhole implements AdapterInterface
{
    /**
     * Storage.
     *
     * @var array
     */
    protected $streams = [];

    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, ?int $version = null): ?array
    {
        return $file->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function readonly(NodeInterface $node): ?array
    {
        return $file->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCollection(Collection $collection): ?array
    {
        return $collection->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function move(NodeInterface $node, Collection $parent): ?array
    {
        $storage = $node->getAttributes()['storage'];
        $parent = $parent->getAttributes()['storage'];
        $storage['path'] = dirname($parent['path']).DIRECTORY_SEPARATOR.$node->getName();

        return $storage;
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteCollection(Collection $collection): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function undelete(NodeInterface $node): ?array
    {
        return $collection->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name): ?array
    {
        $storage = $node->getAttributes()['storage'];
        $storage['path'] = dirname($storage['path']).DIRECTORY_SEPARATOR.$new_name;

        return $storage;
    }

    /**
     * {@inheritdoc}
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session = null): ObjectId
    {
        $session = new ObjectId();
        $this->streams[(string) $session] = $stream;

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, ObjectId $session): array
    {
        $hash = hash_init('md5');

        if (!isset($this->streams[(string) $session])) {
            throw new Exception\BlobNotFound('temporary blob '.$session.' has not been found');
        }

        $stream = $this->streams[(string) $session];
        $size = 0;

        while (!feof($stream)) {
            $buffer = fgets($stream, 65536);

            if ($buffer === false) {
                continue;
            }

            $size += mb_strlen($buffer, '8bit');
            hash_update($hash, $buffer);
        }

        unset($this->streams[(string) $session]);
        $md5 = hash_final($hash);

        return [
            'reference' => $file->getAttributes()['storage'],
            'size' => $size,
            'hash' => $md5,
        ];
    }
}
