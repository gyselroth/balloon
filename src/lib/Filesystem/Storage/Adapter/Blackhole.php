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

class Blackhole implements AdapterInterface
{
    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node, array $attributes): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, array $attributes): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file, array $attributes)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, $contents): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name, array $attributes): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCollection(Collection $collection, array $attributes): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name, array $attributes): bool
    {
        return true;
    }
}
