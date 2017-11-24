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
     * {@inheritdoc}
     */
    public function preExecuteAsyncJobs(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postExecuteAsyncJobs(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preAuthentication(Auth $auth): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preServerIdentity(Identity $identity, ?array &$attributes): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postServerIdentity(Server $server, User $user): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preCreateFile(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preRestoreFile(File $node, int $version): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postRestoreFile(File $node, int $version): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function prePutFile(File $node, $content, bool $force, array $attributes): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void
    {
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
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
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
    }

    /**
     * {@inheritdoc}
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
