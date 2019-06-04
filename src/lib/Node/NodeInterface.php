<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Node;

use Balloon\Resource\ResourceInterface;
use MongoDB\BSON\ObjectId;
use Sabre\DAV;

interface NodeInterface extends ResourceInterface, DAV\INode
{
    /**
     * Deleted node options.
     */
    const DELETED_EXCLUDE = 0;
    const DELETED_ONLY = 1;
    const DELETED_INCLUDE = 2;

    /**
     * Handle conflicts.
     */
    const CONFLICT_NOACTION = 0;
    const CONFLICT_RENAME = 1;
    const CONFLICT_MERGE = 2;

    /**
     * Meta attributes.
     */
    const META_DESCRIPTION = 'description';
    const META_COLOR = 'color';
    const META_AUTHOR = 'author';
    const META_MAIL = 'mail';
    const META_LICENSE = 'license';
    const META_COPYRIGHT = 'copyright';
    const META_TAGS = 'tags';

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     */
    public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool;

    /**
     * Check if $node is a sub node of any parent nodes of this node.
     */
    public function isSubNode(self $node): bool;

    /**
     * Move node.
     */
    public function setParent(Collection $parent, int $conflict = self::CONFLICT_NOACTION): self;

    /**
     * Copy node.
     */
    public function copyTo(Collection $parent, int $conflict = self::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = self::DELETED_EXCLUDE): self;

    /**
     * Get share id.
     */
    public function getShareId(bool $reference = false): ?ObjectId;

    /**
     * Get share node.
     */
    public function getShareNode(): ?Collection;

    /**
     * Is node marked as readonly?
     */
    public function isReadonly(): bool;

    /**
     * Request is from node owner?
     */
    public function isOwnerRequest(): bool;

    /**
     * Check if node is kind of special.
     */
    public function isSpecial(): bool;

    /**
     * Check if node is a sub node of a share.
     */
    public function isShareMember(): bool;

    /**
     * Is share.
     */
    public function isShare(): bool;

    /**
     * Is share (Reference or master share).
     */
    public function isShared(): bool;

    /**
     * Set the name.
     *
     * @param string $name
     */
    public function setName($name): bool;

    /**
     * Check name.
     */
    public function checkName(string $name): string;

    /**
     * Get the name.
     */
    public function getName(): string;

    /**
     * Get attribute.
     */
    public function getAttributes(): array;

    /**
     * Undelete.
     *
     * @param string $recursion
     */
    public function undelete(int $conflict = self::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): bool;

    /**
     * Is node deleted?
     */
    public function isDeleted(): bool;

    /**
     * Get unique id.
     *
     * @return ObjectId
     */
    public function getId(): ?ObjectId;

    /**
     * Get parent.
     *
     * @return Collection
     */
    public function getParent(): ?Collection;

    /**
     * Get parents.
     */
    public function getParents(?self $node = null, array $parents = []): array;

    /**
     * Download.
     */
    public function get();
}
