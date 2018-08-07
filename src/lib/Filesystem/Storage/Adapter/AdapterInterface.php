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
     */
    public function openReadStream(File $file, array $attributes);

    /**
     * Store file.
     */
    public function storeFile(File $file, $contents);

    /**
     * Create collection.
     */
    public function createCollection(Collection $parent, string $name, array $attributes): array;

    /**
     * Delete collection.
     */
    public function deleteCollection(Collection $collection, array $attributes): bool;

    /**
     * Rename node.
     */
    public function rename(NodeInterface $node, string $new_name, array $attributes): bool;
}
