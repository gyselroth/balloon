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

interface AppInterface
{
    /**
     * Init app.
     *
     * @return bool
     */
    public function init(): bool;

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool;

    /**
     * Get attributes.
     *
     * @param NodeInterface $node
     * @param array         $attributes
     *
     * @return array
     */
    public function getAttributes(NodeInterface $node, array $attributes = []): array;

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string;
}
