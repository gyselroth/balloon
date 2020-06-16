<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\Hook;

use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook\AbstractHook;
use MongoDB\BSON\ObjectId;

class Lock extends AbstractHook
{
    /**
     * {@inheritdoc}
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
        $this->validateRequest($parent);
    }

    /**
     * {@inheritdoc}
     */
    public function preCreateFile(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
        $this->validateRequest($parent);
    }

    /**
     * {@inheritdoc}
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        $this->validateRequest($node);
    }

    /**
     * {@inheritdoc}
     */
    public function preRestoreFile(File $node, int $version): void
    {
        $this->validateRequest($node);
    }

    /**
     * {@inheritdoc}
     */
    public function prePutFile(File $node, ObjectId $session): void
    {
        $this->validateRequest($node);
    }

    /**
     * {@inheritdoc}
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preSaveNodeAttributes(
        NodeInterface $node,
        array &$save_attributes,
        array &$remove_attributes,
        ?string $recursion,
        bool $recursion_first
    ): void {
        $this->validateRequest($node);
    }

    /**
     * Validate request.
     */
    protected function validateRequest(NodeInterface $node): bool
    {
        if (isset($_SERVER['ORIG_SCRIPT_NAME']) && preg_match('#^/index.php/(api|wopi)/#', $_SERVER['ORIG_SCRIPT_NAME']) && $node->isLocked()) {
            $token = $_SERVER['HTTP_LOCK_TOKEN'] ?? null;
            $lock = $node->getLock();

            if ($token === null) {
                throw new Exception\Locked('node is temporarily locked and can not be changed');
            }

            if ($lock['id'] !== $token) {
                throw new Exception\LockIdMissmatch('node is temporarily locked and requested id does not match the lock token');
            }
        }

        return true;
    }
}
