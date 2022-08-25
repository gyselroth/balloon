<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use MongoDB\BSON\ObjectId;
use Sabre\DAV;

interface NodeInterface extends DAV\INode
{
    /**
     * Deleted node options.
     */
    public const DELETED_EXCLUDE = 0;
    public const DELETED_ONLY = 1;
    public const DELETED_INCLUDE = 2;

    /**
     * Handle conflicts.
     */
    public const CONFLICT_NOACTION = 0;
    public const CONFLICT_RENAME = 1;
    public const CONFLICT_MERGE = 2;

    /**
     * Meta attributes.
     */
    public const META_DESCRIPTION = 'description';
    public const META_COLOR = 'color';
    public const META_AUTHOR = 'author';
    public const META_MAIL = 'mail';
    public const META_LICENSE = 'license';
    public const META_COPYRIGHT = 'copyright';
    public const META_TAGS = 'tags';

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     */
    public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool;

    /**
     * Set filesystem.
     */
    public function setFilesystem(Filesystem $fs): self;

    /**
     * Get filesystem.
     */
    public function getFilesystem(): Filesystem;

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
