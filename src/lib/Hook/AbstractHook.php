<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Auth;
use Micro\Auth\Identity;

abstract class AbstractHook implements HookInterface
{
    /**
     * {@inheritDoc}
     */
    public function preExecuteAsyncJobs(): void
    {
    }


    /**
     * {@inheritDoc}
     */
    public function postExecuteAsyncJobs(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preAuthentication(Auth $auth): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preServerIdentity(Identity $identity, ?array &$attributes): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postServerIdentity(Server $server, User $user): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preCreateFile(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preCopyCollection(
        Collection $node,
        Collection $parent,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void {
    }

    /**
     * {@inheritDoc}
     */
    public function postCopyCollection(
        Collection $node,
        Collection $parent,
        Collection $new_node,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void {
    }

    /**
     * {@inheritDoc}
     */
    public function preCopyFile(
        File $node,
        Collection $parent,
       int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void {
    }

    /**
     * {@inheritDoc}
     */
    public function postCopyFile(
        File $node,
        Collection $parent,
        File $new_node,
       int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void {
    }

    /**
     * {@inheritDoc}
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preRestoreFile(File $node, int $version): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postRestoreFile(File $node, int $version): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function prePutFile(File $node, $content, bool $force, array $attributes): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function preSaveNodeAttributes(
        NodeInterface $node,
        array &$save_attributes,
        array &$remove_attributes,
        ?string $recursion,
        bool $recursion_first
    ): void {
    }

    /**
     * {@inheritDoc}
     */
    public function postSaveNodeAttributes(
        NodeInterface $node,
        array $save_attributes,
        array $remove_attributes,
        ?string $recursion,
        bool $recursion_first
    ): void {
    }
}
