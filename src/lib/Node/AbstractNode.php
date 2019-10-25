<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Node;

use Balloon\Acl;
use Balloon\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Exception;
use Balloon\Hook;
use Balloon\Resource\AbstractResource;
use Balloon\Server;
use Balloon\Server\User;
use MimeType\MimeType;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Normalizer;
use Psr\Log\LoggerInterface;
use ZipStream\ZipStream;
use Balloon\Collection\CollectionInterface;

abstract class AbstractNode extends AbstractResource /*implements NodeInterface*/
{
    /**
     * Is node marked as readonly?
     */
    public function isReadonly(): bool
    {
        return (bool)$this->resource['readonly'];
    }


     /**
      * Get the name.
     */
     public function getName(): string
    {
         return $this->resource['name'] ?? '';
     }


    /**
     * Get parent.
     */
    public function getParent(): ?CollectionInterface
    {
        return $this->parent;
    }

    /**
     * Get parents.
     */
    public function getParents(?NodeInterface $node = null, array $parents = []): array
    {
        if (null === $node) {
            $node = $this;
        }

        if ($node->isInRoot()) {
            return $parents;
        }
        $parent = $node->getParent();
        $parents[] = $parent;

        return $node->getParents($parent, $parents);
    }


    /**
     * Get owner.
     */
    public function getOwner(): ObjectId
    {
        return $this->resource['owner'];
    }
    /**
     * Resolve node path.
     */
    public function getPath(): string
    {
        $path = '';
        foreach (array_reverse($this->getParents()) as $parent) {
            $path .= DIRECTORY_SEPARATOR.$parent->getName();
        }

        $path .= DIRECTORY_SEPARATOR.$this->getName();

        return $path;
    }
     /**
     * Check if node is in root.
     */
    public function isInRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * Lock file.
     */
    public function lock(string $identifier, ?int $ttl = 1800): NodeInterface
    {
        if ($this->isLocked()) {
            if ($identifier !== $this->lock['id']) {
                throw new Exception\LockIdMissmatch('the unlock id must match the current lock id');
            }
        }

        $this->lock = $this->prepareLock($identifier, $ttl ?? 1800);
        $this->save(['lock']);

        return $this;
    }

    /**
     * Get lock.
     */
    public function getLock(): array
    {
        if (!$this->isLocked()) {
            throw new Exception\NotLocked('node is not locked');
        }

        return $this->resource['lock'];
    }

    public function isSubNode(NodeInterface $node): bool
    {
        if ($node->getId() == $this->id) {
            return true;
        }

        foreach ($node->getParents() as $node) {
            if ($node->getId() == $this->id) {
                return true;
            }
        }

        if ($this->isRoot()) {
            return true;
        }

        return false;
}


    /**
     * Is locked?
     */
    public function isLocked(): bool
    {
        if ($this->resource['lock'] === null) {
            return false;
        }
        if ($this->resource['lock']['expire'] <= new UTCDateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Unlock.
     */
    public function unlock(?string $identifier = null): NodeInterface
    {
        if (!$this->isLocked()) {
            throw new Exception\NotLocked('node is not locked');
        }

        if ($this->lock['owner'] != $this->user->getId()) {
            throw new Exception\Forbidden('node is locked by another user');
        }

        if ($identifier !== null && $this->lock['id'] !== $identifier) {
            throw new Exception\LockIdMissmatch('the unlock id must match the current lock id');
        }

        $this->lock = null;

        return $this;
    }

}
