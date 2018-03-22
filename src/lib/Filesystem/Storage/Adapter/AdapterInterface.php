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
     *
     * @param NodeInterface $node
     * @param array         $attributes
     *
     * @return bool
     */
    public function hasNode(NodeInterface $node, array $attributes): bool;

    /**
     * Delete file.
     *
     * @param string $id
     * @param array  $attributes
     *
     * @return bool
     */
    public function deleteFile(File $file, array $attributes): bool;

    /**
     * Get stored file.
     *
     * @param File  $file
     * @param array $attributes
     *
     * @return resource
     */
    public function getFile(File $file, array $attributes);

    /**
     * Store file.
     *
     * @param File     $file
     * @param resource $contents
     *
     * @return mixed
     */
    public function storeFile(File $file, $contents);

    /**
     * Create collection.
     *
     * @param Collection $collection
     *
     * @return mixed
     */
    public function createCollection(Collection $collection);
}
