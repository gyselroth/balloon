<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use Balloon\Filesystem\Node\File;

interface AdapterInterface
{
    /**
     * Return match.
     */
    public function match(File $file): bool;

    /**
     * Match adapter for preview.
     */
    public function matchPreview(File $file): bool;

    /**
     * Supported formats.
     */
    public function getSupportedFormats(File $file): array;

    /**
     * Convert.
     *
     * @return resource
     */
    public function convert(File $file, string $format);

    /**
     * Create preview.
     *
     * @return resource
     */
    public function createPreview(File $file);
}
