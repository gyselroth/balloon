<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;

class Delta extends AbstractHook
{
    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        if (true === $clone) {
            return;
        }

        if ($node->isReference()) {
            $operation = 'addCollectionReference';
        } else {
            $operation = 'addCollection';
        }

        $event = [
            'parent' => $parent->getRealId(),
        ];

        $parent->getFilesystem()->getDelta()->add($operation, $node, $event);
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
        $event = [
            'parent' => $parent->getRealId(),
        ];

        $parent->getFilesystem()->getDelta()->add('copyCollection', $new_node, $event);
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
        $event = [
            'parent' => $parent->getRealId(),
        ];

        $parent->getFilesystem()->getDelta()->add('copyFile', $new_node, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
        if (true === $clone) {
            return;
        }

        $event = [
            'parent' => $parent->getRealId(),
        ];

        $parent->getFilesystem()->getDelta()->add('addFile', $node, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        if ($node->isReference()) {
            if (true === $force) {
                $operation = 'forceDeleteCollectionReference';
            } else {
                $operation = 'deleteCollectionReference';
            }
        } else {
            if (true === $force) {
                $operation = 'forceDeleteCollection';
            } else {
                $operation = 'deleteCollection';
            }
        }

        $event['force'] = $force;
        $event['parent'] = $node->getAttributes()['parent'];
        $node->getFilesystem()->getDelta()->add($operation, $node, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        if (true === $force) {
            $operation = 'forceDeleteFile';
        } else {
            $operation = 'deleteFile';
        }

        $event['parent'] = $node->getAttributes()['parent'];
        $event['force'] = $force;
        $node->getFilesystem()->getDelta()->add($operation, $node, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        $raw = $node->getRawAttributes();
        $log = [
            'parent' => $node->getParent()->getRealId(),
        ];

        if ($node instanceof Collection) {
            $suffix = 'Collection';
        } else {
            $suffix = 'File';
        }

        if ($node->isReference()) {
            $suffix2 = 'Reference';
        } elseif ($node->isShare()) {
            $suffix2 = 'Share';
        } else {
            $suffix2 = '';
        }

        if (in_array('shared', $attributes, true) && !$node->isShared() && array_key_exists('shared', $raw) && true === $raw['shared']) {
            $operation = 'unshareCollection';
        } elseif (in_array('parent', $attributes, true) && $raw['parent'] !== $node->getAttributes()['parent']) {
            $operation = 'move'.$suffix.$suffix2;
            $log['previous'] = [
                'parent' => $raw['parent'],
            ];
        } elseif (in_array('name', $attributes, true) && $raw['name'] !== $node->getName()) {
            $operation = 'rename'.$suffix.$suffix2;
            $log['previous'] = [
                'name' => $raw['name'],
            ];
        } elseif (in_array('deleted', $attributes, true) && $raw['deleted'] !== $node->getAttributes()['deleted']
            && !$node->isDeleted()) {
            $operation = 'undelete'.$suffix.$suffix2;
        } elseif (in_array('shared', $attributes, true) && $raw['shared'] !== $node->isShare() && $node->isShare()) {
            $operation = 'add'.$suffix.$suffix2;

            if ('addCollectionShare' === $operation) {
                $this->updateExistingDeltaShareMember($node);
            }
        } elseif (in_array('shared', $attributes, true) && $raw['shared'] !== $node->isShare() && !$node->isShare()) {
            $operation = 'delete'.$suffix.$suffix2;
        } elseif ($node instanceof File && $node->getVersion() !== $raw['version'] && $raw['version'] !== 0) {
            $history = $node->getHistory();
            $last = end($history);

            if ($last['version'] === $node->getVersion()) {
                switch ($last['type']) {
                    case File::HISTORY_EDIT:
                        $operation = 'editFile';
                        $log['previous'] = [
                            'version' => $raw['version'],
                        ];

                        break;
                    case File::HISTORY_RESTORE:
                        $operation = 'restoreFile';
                        $log['previous'] = [
                            'version' => $raw['version'],
                        ];

                        break;
                }
            } else {
                $operation = 'editFile';
            }
        }

        if (isset($operation)) {
            $node->getFilesystem()->getDelta()->add($operation, $node, $log);
        }
    }

    /**
     * Update share delta entries.
     */
    protected function updateExistingDeltaShareMember(NodeInterface $node): bool
    {
        $toset = $node->getFilesystem()->findNodesByFilterRecursiveToArray($node);
        $action = [
            '$set' => [
                'share' => $node->getRealId(),
            ],
        ];

        $node->getFilesystem()->getDatabase()->delta->updateMany([
             'node' => [
                 '$in' => $toset,
            ],
        ], $action);

        return true;
    }
}
