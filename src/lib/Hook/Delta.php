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
use Balloon\Helper;
use MongoDB\BSON\ObjectId;

class Delta extends AbstractHook
{
    /*public function __construct(Database $db, Server $server)
    {
        $this->db = $db;
        $this->f
    }*/

    /**
     * Client.
     *
     * @var array
     */
    protected $client = [
        'type' => null,
        'app' => null,
        'v' => null,
        'hostname' => null,
    ];

    /**
     * Init.
     */
    public function init(): void
    {
        if (PHP_SAPI === 'cli') {
            $this->client = [
                'type' => 'system',
                'app' => 'system',
                'v' => null,
                'hostname' => null,
            ];
        } else {
            if (isset($_SERVER['HTTP_X_CLIENT'])) {
                $parts = explode('|', Helper::filter($_SERVER['HTTP_X_CLIENT']));
                $count = count($parts);

                if (3 === $count) {
                    $this->client['v'] = $parts[1];
                    $this->client['hostname'] = $parts[2];
                } elseif (2 === $count) {
                    $this->client['v'] = $parts[1];
                }

                $this->client['app'] = $parts[0];
            }

            if (isset($_SERVER['PATH_INFO'])) {
                $parts = explode('/', $_SERVER['PATH_INFO']);
                if (count($parts) >= 2) {
                    $this->client['type'] = $parts[1];
                }
            }
        }
    }

    /**
     * Run: postCreateCollection.
     *
     * Executed post a directory was created
     *
     * @param Collection $parent
     * @param Collection $node
     * @param bool       $clone
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
            'operation' => $operation,
            'node' => $node->getId(),
            'parent' => $parent->getRealId(),
            'name' => $node->getName(),
            'client' => $this->client,
            'owner' => $this->getEventOwner($node),
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }

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
    ): void {
        if (false === $recursion_first) {
            return;
        }

        $event = [
            'operation' => 'copyCollection',
            'node' => $new_node->getId(),
            'parent' => $parent->getRealId(),
            'name' => $new_node->getName(),
            'client' => $this->client,
            'owner' => $this->getEventOwner($new_node),
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }

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
    ): void {
        if (false === $recursion_first) {
            return;
        }

        $event = [
            'operation' => 'copyFile',
            'node' => $new_node->getId(),
            'parent' => $parent->getRealId(),
            'name' => $new_node->getName(),
            'client' => $this->client,
            'owner' => $this->getEventOwner($new_node),
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }

    /**
     * Run: postCreateFile.
     *
     * Executed post a file was created
     *
     * @param Collection $parent
     * @param File       $node
     * @param bool       $clone
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
        if (true === $clone) {
            return;
        }

        $event = [
            'operation' => 'addFile',
            'node' => $node->getId(),
            'parent' => $parent->getRealId(),
            'name' => $node->getName(),
            'client' => $this->client,
            'owner' => $this->getEventOwner($node),
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }

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
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        $attributes = $node->getAttributes(['parent', 'name']);
        $attributes['node'] = $node->getId();

        if ($node->isReference()) {
            if (true === $force) {
                $attributes['operation'] = 'forceDeleteCollectionReference';
            } else {
                $attributes['operation'] = 'deleteCollectionReference';
            }
        } else {
            if (true === $force) {
                $attributes['operation'] = 'forceDeleteCollection';
            } else {
                $attributes['operation'] = 'deleteCollection';
            }
        }

        if ($node->isShareMember()) {
            $attributes['share'] = $node->getShareId();
        }

        $attributes['client'] = $this->client;
        $attributes['owner'] = $this->getEventOwner($node);
        $attributes['force'] = $force;
        $node->getFilesystem()->getDelta()->add($attributes);
    }

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
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        $attributes = $node->getAttributes(['parent', 'name']);
        $attributes['node'] = $node->getId();

        if (true === $force) {
            $attributes['operation'] = 'forceDeleteFile';
        } else {
            $attributes['operation'] = 'deleteFile';
        }

        if ($node->isShareMember()) {
            $attributes['share'] = $node->getShareId();
        }

        $attributes['client'] = $this->client;
        $attributes['owner'] = $this->getEventOwner($node);
        $attributes['force'] = $force;
        $node->getFilesystem()->getDelta()->add($attributes);
    }

    /**
     * Run: postSaveNodeAttributes.
     *
     * Executed post node attributes were saved to mongodb
     *
     * @param NodeInterface $node
     * @param array         $attributes
     * @param array         $remove
     * @param string        $recursion
     * @param bool          $recursion_first
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if (false === $recursion_first) {
            return;
        }

        $raw = $node->getRawAttributes();
        $log = [
            'name' => $node->getName(),
            'parent' => $node->getParent()->getRealId(),
            'node' => $node->getId(),
        ];

        if ($node->isShareMember()) {
            $log['share'] = $node->getShareId();
        }

        $log['owner'] = $this->getEventOwner($node);

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
            $log['operation'] = 'unshareCollection';
        } elseif (in_array('parent', $attributes, true) && $raw['parent'] !== $node->getAttributes()['parent']) {
            $log['operation'] = 'move'.$suffix.$suffix2;
            $log['previous'] = [
                'parent' => $raw['parent'],
            ];
        } elseif (in_array('name', $attributes, true) && $raw['name'] !== $node->getName()) {
            $log['operation'] = 'rename'.$suffix.$suffix2;
            $log['previous'] = [
                'name' => $raw['name'],
            ];
        } elseif (in_array('deleted', $attributes, true) && $raw['deleted'] !== $node->getAttributes()['deleted']
            && !$node->isDeleted()) {
            $log['operation'] = 'undelete'.$suffix.$suffix2;
        } elseif (in_array('shared', $attributes, true) && $raw['shared'] !== $node->isShare() && $node->isShare()) {
            $log['operation'] = 'add'.$suffix.$suffix2;

            if ('addCollectionShare' === $log['operation']) {
                $this->updateExistingDeltaShareMember($node);
            }
        } elseif (in_array('shared', $attributes, true) && $raw['shared'] !== $node->isShare() && !$node->isShare()) {
            $log['operation'] = 'delete'.$suffix.$suffix2;
        } elseif ($node instanceof File && $node->getVersion() !== $raw['version']) {
            $history = $node->getHistory();
            $last = end($history);

            switch ($last['type']) {
                case File::HISTORY_EDIT:
                    $log['operation'] = 'editFile';
                    $log['previous'] = [
                        'version' => $raw['version'],
                    ];

                    break;
                case File::HISTORY_RESTORE:
                    $log['operation'] = 'restoreFile';
                    $log['previous'] = [
                        'version' => $raw['version'],
                    ];

                    break;
            }
        }

        $log['client'] = $this->client;

        if (isset($log['operation'])) {
            $node->getFilesystem()->getDelta()->add($log);
        }
    }

    /**
     * Get Event owner id.
     *
     * @param NodeInterface $node
     *
     * @return ObjectId
     */
    protected function getEventOwner(NodeInterface $node): ObjectId
    {
        $user = $node->getFilesystem()->getUser();
        if (null === $user) {
            return $node->getOwner();
        }

        return $user->getId();
    }

    /**
     * Update share delta entries.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    protected function updateExistingDeltaShareMember(NodeInterface $node): bool
    {
        $toset = $node->getChildrenRecursive($node->getRealId());
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
