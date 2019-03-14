<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\RoleInterface;
use Balloon\Server\User;
use Micro\Auth\Auth;
use Micro\Auth\Identity;
use MongoDB\BSON\ObjectId;

interface HookInterface
{
    /**
     * Run: preExecuteAsyncJobs.
     */
    public function preExecuteAsyncJobs(): void;

    /**
     * Run: preUpdateUser.
     */
    public function preUpdateUser(User $user, array &$attributes = []): void;

    /**
     * Run: postUpdateUser.
     */
    public function postUpdateUser(User $user, array $attributes = []): void;

    /**
     * Run: preDecorateRole.
     */
    public function preDecorateRole(RoleInterface $role, array &$attributes = []): void;

    /**
     * Run: preAuthentication.
     *
     * Executed before authentication
     */
    public function preAuthentication(Auth $auth): void;

    /**
     * Run: preServerIdentity.
     *
     * Executed after authentication but before the identity gets authenticated with the server
     *
     * @param User $user
     */
    public function preServerIdentity(Identity $identity, ?User &$user): void;

    /**
     * Run: postCreateCollection.
     *
     * Executed authenticated with the server
     */
    public function postServerIdentity(User $user): void;

    /**
     * Run: preRestoreFile.
     *
     * Executed pre version rollback
     */
    public function preRestoreFile(File $node, int $version): void;

    /**
     * Run: postRestoreFile.
     *
     * Executed post version rollback
     */
    public function postRestoreFile(File $node, int $version): void;

    /**
     * Run: prePutFile.
     *
     * Executed pre a put file request
     */
    public function prePutFile(File $node, ObjectId $session): void;

    /**
     * Run: postPutFile.
     *
     * Executed post a put file request
     */
    public function postPutFile(File $node): void;

    /**
     * Run: preCreateCollection.
     *
     * Executed pre a directory will be created
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void;

    /**
     * Run: postCreateCollection.
     *
     * Executed post a directory was created
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void;

    /**
     * Run: preCreateFile.
     *
     * Executed pre a create will be created
     */
    public function preCreateFile(Collection $parent, string &$name, array &$attributes, bool $clone): void;

    /**
     * Run: postCreateFile.
     *
     * Executed post a file was created
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void;

    /**
     * Run: preCopyCollection.
     *
     * Executed pre a directory will be cloned
     *
     * @param string $recursion
     */
    public function preCopyCollection(
        Collection $node,
        Collection $parent,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void;

    /**
     * Run: postCopyCollection.
     *
     * Executed post a directory will be cloned
     *
     * @param string $recursion
     */
    public function postCopyCollection(
        Collection $node,
        Collection $parent,
        Collection $new_node,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void;

    /**
     * Run: preCopyFile.
     *
     * Executed pre a file will be cloned
     *
     * @param string $recursion
     */
    public function preCopyFile(
        File $node,
        Collection $parent,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void;

    /**
     * Run: postCopyFile.
     *
     * Executed post a file will be cloned
     *
     * @param string $recursion
     */
    public function postCopyFile(
        File $node,
        Collection $parent,
        File $new_node,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void;

    /**
     * Run: preDeleteCollection.
     *
     * Executed pre a directory will be deleted
     *
     * @param string $recursion
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: postDeleteCollection.
     *
     * Executed post a directory was deleted
     *
     * @param string $recursion
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: preDeleteFile.
     *
     * Executed pre a file will be deleted
     *
     * @param string $recursion
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: postDeleteFile.
     *
     * Executed post a file was deleted
     *
     * @param string $recursion
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: preSaveNodeAttributes.
     *
     * Executed pre node attributes will be saved to mongodb
     *
     * @param string $recursion
     */
    public function preSaveNodeAttributes(
        NodeInterface $node,
        array &$save_attributes,
        array &$remove_attributes,
        ?string $recursion,
        bool $recursion_first
    ): void;

    /**
     * Run: postSaveNodeAttributes.
     *
     * Executed post node attributes were saved to mongodb
     *
     * @param string $recursion
     */
    public function postSaveNodeAttributes(
        NodeInterface $node,
        array $save_attributes,
        array $remove_attributes,
        ?string $recursion,
        bool $recursion_first
    ): void;
}
