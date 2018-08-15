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
    public function deleteFile(File $file, ?int $version = null): bool;

    /**
     * Delete file completely.
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool;

    /**
     * Get stored file.
     */
    public function openReadStream(File $file);

    /**
     * Store temporary file.
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session = null): ObjectId;

    /**
     * Store file.
     */
    public function storeFile(File $file, ObjectId $session): array;

    /**
     * Create collection.
     */
    public function createCollection(Collection $parent, string $name): array;

    /**
     * Delete collection.
     */
    public function deleteCollection(Collection $collection): bool;

    /**
     * Delete collection.
     */
    public function forceDeleteCollection(Collection $collection): bool;

    /**
     * Rename node.
     */
    public function rename(NodeInterface $node, string $new_name): bool;
}
