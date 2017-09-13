<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use \Balloon\Filesystem\Node\File;
use \Balloon\Converter\Result;

interface AdapterInterface
{
    /**
     * Return match
     *
     * @param   File $file
     * @return  bool
     */
    public function match(File $file): bool;
    

    /**
     * Supported formats
     *
     * @param   File $file
     * @return  array
     */
    public function getSupportedFormats(File $file): array;


    /**
     * Convert
     *
     * @param  File $file
     * @param  string $format
     * @return Result
     */
    public function convert(File $file, string $format): Result;
}
