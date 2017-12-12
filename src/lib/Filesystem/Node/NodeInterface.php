<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
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
    const META_COORDINATE = 'coordinate';

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     *
     * @param bool   $force
     * @param string $recursion
     * @param bool   $recursion_first
     *
     * @return bool
     */
    public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool;

    /**
     * Set filesystem.
     *
     * @return NodeInterface
     */
    public function setFilesystem(Filesystem $fs): self;

    /**
     * Get filesystem.
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem;

    /**
     * Check if $node is a sub node of any parent nodes of this node.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    public function isSubNode(self $node): bool;

    /**
     * Move node.
     *
     * @param Collection $parent
     * @param int        $conflict
     *
     * @return NodeInterface
     */
    public function setParent(Collection $parent, int $conflict = self::CONFLICT_NOACTION): self;

    /**
     * Copy node.
     *
     * @param Collection $parent
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
     *
     * @return NodeInterface
     */
    public function copyTo(Collection $parent, int $conflict = self::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): self;

    /**
     * Get share id.
     *
     * @param bool $reference
     *
     * @return ObjectId
     */
    public function getShareId(bool $reference = false): ?ObjectId;

    /**
     * Get share node.
     *
     * @param bool $reference
     *
     * @return Collection
     */
    public function getShareNode(): ?Collection;

    /**
     * Is node marked as readonly?
     *
     * @return bool
     */
    public function isReadonly(): bool;

    /**
     * Request is from node owner?
     *
     * @return bool
     */
    public function isOwnerRequest(): bool;

    /**
     * Check if node is kind of special.
     *
     * @return bool
     */
    public function isSpecial(): bool;

    /**
     * Check if node is a sub node of a share.
     *
     * @return bool
     */
    public function isShareMember(): bool;

    /**
     * Is share.
     *
     * @return bool
     */
    public function isShare(): bool;

    /**
     * Is share (Reference or master share).
     *
     * @return bool
     */
    public function isShared(): bool;

    /**
     * Set the name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function setName($name): bool;

    /**
     * Check name.
     *
     * @param string $name
     *
     * @return string
     */
    public function checkName(string $name): string;

    /**
     * Get the name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get attribute.
     *
     * @return array
     */
    public function getAttributes(): array;

    /**
     * Undelete.
     *
     * @param int    $conflict
     * @param string $recursion
     * @param bool   $recursion_first
     *
     * @return bool
     */
    public function undelete(int $conflict = self::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): bool;

    /**
     * Is node deleted?
     *
     * @return bool
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
     *
     * @param array $parents
     *
     * @return array
     */
    public function getParents(?self $node = null, array $parents = []): array;

    /**
     * Download.
     */
    public function get();
}
