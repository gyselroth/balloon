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
     *
     *
     * @return resource
     */
    public function getFile(File $file, array $attributes);

    /**
     * Store file.
     *
     * @param resource $contents
     */
    public function storeFile(File $file, $contents);

    /**
     * Create collection.
     */
    public function createCollection(Collection $collection);
}
