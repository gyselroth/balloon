<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use \Balloon\Helper;
use \Balloon\User;
use \Balloon\Filesystem\Node\Collection;
use \Balloon\Filesystem\Node\File;
use \Balloon\Filesystem\Node\NodeInterface;
use \Balloon\Filesystem;
use \MongoDB\BSON\ObjectID;
use \Balloon\Hook\AbstractHook;

class Delta extends AbstractHook
{
    /**
     * Client
     *
     * @var array
     */
    protected $client = [
        'type'      => null,
        'app'       => null,
        'v'         => null,
        'hostname'  => null
    ];


    /**
     * Init
     *
     * @return void
     */
    public function init(): void
    {
        if (php_sapi_name() === 'cli') {
            $this->client = [
                'type'     => 'system',
                'app'      => 'system',
                'v'        => null,
                'hostname' => null
            ];
        } else {
            if (isset($_SERVER['HTTP_X_CLIENT'])) {
                $parts = explode('|', Helper::filter($_SERVER['HTTP_X_CLIENT']));
                $count = count($parts);

                if ($count === 3) {
                    $this->client['v']        = $parts[1];
                    $this->client['hostname'] = $parts[2];
                } elseif ($count === 2) {
                    $this->client['v']        = $parts[1];
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
     * Run: postCreateCollection
     *
     * Executed post a directory was created
     *
     * @param   Collection $parent
     * @param   Collection $node
     * @param   bool $clone
     * @return  void
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        if ($clone === true) {
            return;
        }

        if ($node->isReference()) {
            $operation = 'addCollectionReference';
        } else {
            $operation = 'addCollection';
        }

        $event = [
            'operation' => $operation,
            'node'      => $node->getId(),
            'parent'    => $parent->getRealId(),
            'name'      => $node->getName(),
            'client'    => $this->client,
            'owner'     => $this->getEventOwner($node),
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }


    /**
     * Run: postCopyCollection
     *
     * Executed post a directory will be cloned
     *
     * @param   Collection $node
     * @param   Collection $parent
     * @param   Collection $new_node
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postCopyCollection(
        Collection $node,
        Collection $parent,
        Collection $new_node,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void {
        if ($recursion_first === false) {
            return;
        }

        $event = [
            'operation' => 'copyCollection',
            'node'      => $new_node->getId(),
            'parent'    => $parent->getRealId(),
            'name'      => $new_node->getName(),
            'client'    => $this->client,
            'owner'     => $this->getEventOwner($new_node)
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }


    /**
     * Run: postCopyFile
     *
     * Executed post a file will be cloned
     *
     * @param   File $node
     * @param   Collection $parent
     * @param   File $new_node
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postCopyFile(
        File $node,
        Collection $parent,
        File $new_node,
        int $conflict,
        ?string $recursion,
        bool $recursion_first
    ): void {
        if ($recursion_first === false) {
            return;
        }

        $event = [
            'operation' => 'copyFile',
            'node'      => $new_node->getId(),
            'parent'    => $parent->getRealId(),
            'name'      => $new_node->getName(),
            'client'    => $this->client,
            'owner'     => $this->getEventOwner($new_node)
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }


    /**
     * Run: postCreateFile
     *
     * Executed post a file was created
     *
     * @param   Collection $parent
     * @param   File $node
     * @param   bool $clone
     * @return  void
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void
    {
        if ($clone === true) {
            return;
        }

        $event = [
            'operation' => 'addFile',
            'node'      => $node->getId(),
            'parent'    => $parent->getRealId(),
            'name'      => $node->getName(),
            'client'    => $this->client,
            'owner'     => $this->getEventOwner($node)
        ];

        if ($node->isShareMember()) {
            $event['share'] = $node->getShareId();
        }

        $parent->getFilesystem()->getDelta()->add($event);
    }


    /**
     * Run: postDeleteCollection
     *
     * Executed post a directory was deleted
     *
     * @param   Collection $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if ($recursion_first === false) {
            return;
        }

        $attributes = $node->getAttributes(['parent', 'name']);
        $attributes['node']      = $node->getId();


        if ($node->isReference()) {
            if ($force === true) {
                $attributes['operation'] = 'forceDeleteCollectionReference';
            } else {
                $attributes['operation'] = 'deleteCollectionReference';
            }
        } else {
            if ($force === true) {
                $attributes['operation'] = 'forceDeleteCollection';
            } else {
                $attributes['operation'] = 'deleteCollection';
            }
        }

        if ($node->isShareMember()) {
            $attributes['share'] = $node->getShareId();
        }

        $attributes['client']= $this->client;
        $attributes['owner'] = $this->getEventOwner($node);
        $attributes['force'] = $force;
        $node->getFilesystem()->getDelta()->add($attributes);
    }


    /**
     * Get Event owner id
     *
     * @param   NodeInterface $node
     * @return  ObjectID
     */
    protected function getEventOwner(NodeInterface $node): ObjectID
    {
        $user = $node->getFilesystem()->getUser();
        if ($user === null) {
            return $node->getOwner();
        } else {
            return $user->getId();
        }
    }


    /**
     * Run: postDeleteFile
     *
     * Executed post a file was deleted
     *
     * @param   File $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if ($recursion_first === false) {
            return;
        }

        $attributes = $node->getAttributes(['parent', 'name']);
        $attributes['node']      = $node->getId();

        if ($force === true) {
            $attributes['operation'] = 'forceDeleteFile';
        } else {
            $attributes['operation'] = 'deleteFile';
        }

        if ($node->isShareMember()) {
            $attributes['share'] = $node->getShareId();
        }

        $attributes['client']= $this->client;
        $attributes['owner'] = $this->getEventOwner($node);
        $attributes['force'] = $force;
        $node->getFilesystem()->getDelta()->add($attributes);
    }


    /**
     * Run: postSaveNodeAttributes
     *
     * Executed post node attributes were saved to mongodb
     *
     * @param  NodeInterface $node
     * @param  array $attributes
     * @param  array $remove
     * @param  string $recursion
     * @param  bool $recursion_first
     * @return void
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $attributes, array $remove, ?string $recursion, bool $recursion_first): void
    {
        if ($recursion_first === false) {
            return;
        }

        $raw = $node->getRawAttributes();
        $log = [
            'name'    => $node->getName(),
            'parent'  => $node->getParent()->getRealId(),
            'node'    => $node->getId()
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

        if (in_array('shared', $attributes) && !$node->isShared() && array_key_exists('shared', $raw) && $raw['shared'] === true) {
            $log['operation'] = 'unshareCollection';
        } elseif (in_array('parent', $attributes) && $raw['parent'] !== $node->getAttribute('parent')) {
            $log['operation'] = 'move'.$suffix.$suffix2;
            $log['previous']  = [
                'parent' => $raw['parent']
            ];
        } elseif (in_array('name', $attributes) && $raw['name'] !== $node->getAttribute('name')) {
            $log['operation'] = 'rename'.$suffix.$suffix2;
            $log['previous']  = [
                'name' => $raw['name']
            ];
        } elseif (in_array('deleted', $attributes) && $raw['deleted'] !== $node->getAttribute('deleted')
            && !$node->isDeleted()) {
            $log['operation'] = 'undelete'.$suffix.$suffix2;
        } elseif (in_array('shared', $attributes) && $raw['shared'] !== $node->isShare() && $node->isShare()) {
            $log['operation'] = 'add'.$suffix.$suffix2;

            if ($log['operation'] === 'addCollectionShare') {
                $this->updateExistingDeltaShareMember($node);
            }
        } elseif (in_array('shared', $attributes) && $raw['shared'] !== $node->isShare() && !$node->isShare()) {
            $log['operation'] = 'delete'.$suffix.$suffix2;
        } elseif ($node instanceof File && $node->getVersion() != $raw['version']) {
            $history = $node->getHistory();
            $last    = end($history);

            switch ($last['type']) {
                case File::HISTORY_EDIT:
                    $log['operation'] = 'editFile';
                    $log['previous']  = [
                        'version' => $raw['version']
                    ];

                    break;
                case File::HISTORY_RESTORE:
                    $log['operation'] = 'restoreFile';
                    $log['previous']  = [
                        'version' => $raw['version']
                    ];

                    break;
            }
        }

        $log['client']= $this->client;

        if (isset($log['operation'])) {
            $node->getFilesystem()->getDelta()->add($log);
        }
    }


    /**
     * Update share delta entries
     *
     * @param   NodeInterface $node
     * @return  bool
     */
    protected function updateExistingDeltaShareMember(NodeInterface $node): bool
    {
        $toset = $node->getChildrenRecursive($node->getRealId());
        $action = [
            '$set' => [
                'share' => $node->getRealId()
            ]
        ];

        $node->getFilesystem()->getDatabase()->delta->updateMany([
             'node' => [
                 '$in' => $toset,
            ],
        ], $action);

        return true;
    }
}
