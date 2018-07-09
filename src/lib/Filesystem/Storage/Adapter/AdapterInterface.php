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
    public function hasNode(NodeInterface $node, array $attributes): bool;

    /**
     * Delete file.
     */
    public function deleteFile(File $file, array $attributes): bool;

    /**
     * Get stored file.
     *
     * @return resource
     */
    public function getFile(File $file, array $attributes);

    /**
     * Store file.
     */
    public function storeFile(File $file, ObjectId $session);

    /**
     * Store temporary file.
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session): ObjectId;

    /**
     * Create collection.
     */
    public function createCollection(Collection $collection);
}
