<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Auth\Auth;
use Micro\Auth\Identity;

interface HookInterface
{
    /**
     * Run: preExecuteAsyncJobs.
     */
    public function preExecuteAsyncJobs(): void;

    /**
     * Run: preExecuteAsyncJobs.
     */
    public function postExecuteAsyncJobs(): void;

    /**
     * Run: preAuthentication.
     *
     * Executed before authentication
     *
     * @param Auth $auth
     */
    public function preAuthentication(Auth $auth): void;

    /**
     * Run: preServerIdentity.
     *
     * Executed after authentication but before the identity gets authenticated with the server
     *
     * @param Identity $identity
     * @param User     $user
     */
    public function preServerIdentity(Identity $identity, ?User $user): void;

    /**
     * Run: postCreateCollection.
     *
     * Executed authenticated with the server
     *
     * @param User $user
     */
    public function postServerIdentity(User $user): void;

    /**
     * Run: preRestoreFile.
     *
     * Executed pre version rollback
     *
     * @param File $node
     * @param int  $version
     */
    public function preRestoreFile(File $node, int $version): void;

    /**
     * Run: postRestoreFile.
     *
     * Executed post version rollback
     *
     * @param File $node
     * @param int  $version
     */
    public function postRestoreFile(File $node, int $version): void;

    /**
     * Run: prePutFile.
     *
     * Executed pre a put file request
     *
     * @param File            $node
     * @param resource|string $content
     * @param bool            $force
     * @param array           $attributes
     */
    public function prePutFile(File $node, $content, bool $force, array $attributes): void;

    /**
     * Run: postPutFile.
     *
     * Executed post a put file request
     *
     * @param File            $node
     * @param resource|string $content
     * @param bool            $force
     * @param array           $attributes
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void;

    /**
     * Run: preCreateCollection.
     *
     * Executed pre a directory will be created
     *
     * @param Collection $parent
     * @param string     $name
     * @param array      $attributes
     * @param bool       $clone
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void;

    /**
     * Run: postCreateCollection.
     *
     * Executed post a directory was created
     *
     * @param Collection $parent
     * @param Collection $node
     * @param bool       $clone
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void;

    /**
     * Run: preCreateFile.
     *
     * Executed pre a create will be created
     *
     * @param Collection $parent
     * @param string     $name
     * @param array      $attributes
     * @param bool       $clone
     */
    public function preCreateFile(Collection $parent, string &$name, array &$attributes, bool $clone): void;

    /**
     * Run: postCreateFile.
     *
     * Executed post a file was created
     *
     * @param Collection $parent
     * @param File       $node
     * @param bool       $clone
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void;

    /**
     * Run: preCopyCollection.
     *
     * Executed pre a directory will be cloned
     *
     * @param Collection $node
     * @param Collection $parent
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
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
     * @param Collection $node
     * @param Collection $parent
     * @param Collection $new_node
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
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
     * @param File       $node
     * @param Collection $parent
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
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
     * @param File       $node
     * @param Collection $parent
     * @param File       $new_node
     * @param int        $conflict
     * @param string     $recursion
     * @param bool       $recursion_first
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
     * @param Collection $node
     * @param bool       $force
     * @param string     $recursion
     * @param bool       $recursion_first
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: postDeleteCollection.
     *
     * Executed post a directory was deleted
     *
     * @param Collection $node
     * @param bool       $force
     * @param string     $recursion
     * @param bool       $recursion_first
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: preDeleteFile.
     *
     * Executed pre a file will be deleted
     *
     * @param File   $node
     * @param bool   $force
     * @param string $recursion
     * @param bool   $recursion_first
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: postDeleteFile.
     *
     * Executed post a file was deleted
     *
     * @param File   $node
     * @param bool   $force
     * @param string $recursion
     * @param bool   $recursion_first
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void;

    /**
     * Run: preSaveNodeAttributes.
     *
     * Executed pre node attributes will be saved to mongodb
     *
     * @param NodeInterface $node
     * @param array         $save_attributes
     * @param array         $remove_attributes
     * @param string        $recursion
     * @param bool          $recursion_first
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
     * @param NodeInterface $node
     * @param array         $save_attributes
     * @param array         $remove_attributes
     * @param string        $recursion
     * @param bool          $recursion_first
     */
    public function postSaveNodeAttributes(
        NodeInterface $node,
        array $save_attributes,
        array $remove_attributes,
        ?string $recursion,
        bool $recursion_first
    ): void;
}
