<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;

interface AdapterInterface
{
    /**
     * Return match.
     *
     * @param File $file
     *
     * @return bool
     */
    public function match(File $file): bool;

    /**
     * Supported formats.
     *
     * @param File $file
     *
     * @return array
     */
    public function getSupportedFormats(File $file): array;

    /**
     * Convert.
     *
     * @param File   $file
     * @param string $format
     *
     * @return Result
     */
    public function convert(File $file, string $format): Result;


    /**
     * Create preview
     *
     * @param File   $file
     *
     * @return Result
     */
    public function createPreview(File $file): Result;
}
