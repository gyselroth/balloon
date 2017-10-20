<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App;

use Balloon\Filesystem\Node\NodeInterface;

abstract class AbstractApp implements AppInterface
{
    /**
     * Init.
     *
     * @return bool
     */
    public function init(): bool
    {
        return true;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        return true;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        $class = str_replace('\\', '_', get_class($this));

        return substr($class, 0, strrpos($class, '_'));
    }

    /**
     * Get attributes.
     *
     * @param NodeInterface $node
     * @param array         $attributes
     *
     * @return array
     */
    public function getAttributes(NodeInterface $node, array $attributes = []): array
    {
        return [];
    }
}
