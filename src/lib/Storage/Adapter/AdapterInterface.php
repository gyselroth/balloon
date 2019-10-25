<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Storage\Adapter;

use Balloon\Collection\CollectionInterface;
use Balloon\File\FileInterface;
use Balloon\Node\NodeInterface;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;

interface AdapterInterface
{
    /**
     * Check if file exists.
     */
    public function hasNode(NodeInterface $node): bool;

    /**
     * Delete file (Move to trash if supported).
     */
    public function deleteFile(FileInterface $file, ?int $version = null): ?array;

    /**
     * Delete file completely.
     */
    public function forceDeleteFile(FileInterface $file, ?int $version = null): bool;

    /**
     * Get stored file.
     */
    public function openReadStream(FileInterface $file);

    /**
     * Store temporary file.
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session = null): ObjectId;

    /**
     * Store file.
     */
    public function storeFile(FileInterface $file, ObjectId $session): array;

    /**
     * Create collection.
     */
    public function createCollection(CollectionInterface $parent, string $name): array;

    /**
     * Delete collection.
     */
    public function deleteCollection(CollectionInterface $collection): ?array;

    /**
     * Delete collection.
     */
    public function forceDeleteCollection(CollectionInterface $collection): bool;

    /**
     * Rename node.
     */
    public function rename(NodeInterface $node, string $new_name): ?array;

    /**
     * Move node.
     */
    public function move(NodeInterface $node, CollectionInterface $parent): ?array;

    /**
     * Undelete node.
     */
    public function undelete(NodeInterface $node): ?array;

    /**
     * Readonly.
     */
    public function readonly(NodeInterface $node, bool $readonly = true): ?array;
}
