<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Node\File;

interface AdapterInterface
{
    /**
     * Check if file exists.
     *
     * @param array $attributes
     *
     * @return bool
     */
    public function hasFile(array $attributes): bool;

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
     * @param array $attributes
     *
     * @return resource
     */
    public function getFile(array $attributes);

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
     * Get metadata for a file.
     *
     * @param array $attributes
     *
     * @return array
     */
    public function getFileMeta(array $attributes): array;
}
