<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use \Balloon\Filesystem\Exception;
use \Balloon\Filesystem\Node\File;

interface AdapterInterface
{
    /**
     * Check if file exists
     *
     * @param   File $file
     * @param   array $attributes
     * @return  bool
     */
    public function hasFile(File $file, array $attributes): bool;


    /**
     * Delete file
     *
     * @param   string $id
     * @param   array $attributes
     * @return  bool
     */
    public function deleteFile(File $file, array $attributes): bool;


    /**
     * Get stored file
     *
     * @param  File $file
     * @param  array $attributes
     * @return resource
     */
    public function getFile(File $file, array $attributes);


    /**
     * Store file
     *
     * @param   File $file
     * @param   resource $contents
     * @return  mixed
     */
    public function storeFile(File $file, $contents);
}
