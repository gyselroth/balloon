<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

//use Balloon\Acl;
//luse Balloon\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Resource\AttributeResolver;
use Balloon\Server\User;
use Balloon\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Generator;
use League\Event\Emitter;
use MimeType\MimeType;
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use function MongoDB\BSON\toPHP;
use MongoDB\BSON\UTCDateTime;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Sabre\DAV\IQuota;
use Balloon\Resource\AbstractResource;
use Balloon\Collection\CollectionInterface;
use Balloon\Node\AbstractNode;

class Collection extends AbstractNode implements CollectionInterface //extends AbstractNode implements CollectionInterface, IQuota
{
    /**
     * Root folder.
     */
    const ROOT_FOLDER = '/';

    /**
     * Share acl.
     *
     * @var array
     */
    protected $acl = [];

    /**
     * Share name.
     *
     * @var string
     */
    protected $share_name;

    /**
     * filter.
     *
     * @var string
     */
    protected $filter;

    /**
     * Storage.
     *
     * @var StorageAdapterInterface
     */
    protected $_storage;

    /**
     * Initialize.
     */
  /*  public function __construct(array $attributes,  LoggerInterface $logger, Emitter $hook, Acl $acl, ?Collection $parent, StorageAdapterInterface $storage)
    {
        $this->fs = $fs;
        // $this->server = $fs->getServer();
        // $this->db = $fs->getDatabase();
        // $this->user = $fs->getUser();
        $this->logger = $logger;
        $this->hook = $hook;
        $this->acl = $acl;
        $this->storage = $storage;
        $this->parent = $parent;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        $this->mime = 'inode/directory';
        $this->raw_attributes = $attributes;
        $this->resource = $attributes;
  }*/


    public function __construct(array $resource, ?CollectionInterface $parent, StorageAdapterInterface $storage)
    {
        $this->resource = $resource;
        $this->parent = $parent;
        $this->storage = $storage;
    }








    /**
     * Set node acl.
     */
    public function setAcl(array $acl): NodeInterface
    {
        if (!$this->acl->isAllowed($this, 'm')) {
            throw new ForbiddenException(
                'not allowed to update acl',
                ForbiddenException::NOT_ALLOWED_TO_MANAGE
            );
        }

        if (!$this->isShareMember()) {
            throw new Exception\Conflict('node acl may only be set on share member nodes', Exception\Conflict::NOT_SHARED);
        }

        $this->acl->validateAcl($this->server, $acl);
        $this->acl = $acl;

        //TODO:WRONG
        $this->save(['acl']);

        return $this;
    }

    /**
     * Get ACL.
     */
    public function getAcl(): array
    {
        if ($this->isReference()) {
            $acl = $this->fs->findRawNode($this->getShareId())['acl'];
        } else {
            $acl = $this->acl;
        }

        return $this->acl->resolveAclTable($this->server, $acl);
    }

    /**
     * Get share id.
     */
    public function getShareId(bool $reference = false): ?ObjectId
    {
        if ($this->isReference() && true === $reference) {
            return $this->id;
        }
        if ($this->isShareMember() && true === $reference) {
            return $this->shared;
        }
        if ($this->isShared() && $this->isReference()) {
            return $this->reference;
        }
        if ($this->isShared()) {
            return $this->id;
        }
        if ($this->isShareMember()) {
            return $this->shared;
        }

        return null;
    }

    /**
     * Get share node.
     */
    public function getShareNode(): ?Collection
    {
        if ($this->isShare()) {
            return $this;
        }

        if ($this->isSpecial()) {
            return $this->fs->findNodeById($this->getShareId(true));
        }

        return null;
    }


    /**
     * May write.
     */
    public function mayWrite(): bool
    {
        return Acl::PRIVILEGES_WEIGHT[$this->acl->getAclPrivilege($this)] > Acl::PRIVILEGE_READ;
    }

    /**
     * Request is from node owner?
     */
    public function isOwnerRequest(): bool
    {
        return null !== $this->user && $this->owner == $this->user->getId();
    }

    /**
     * Check if node is kind of special.
     */
    public function isSpecial(): bool
    {
        if ($this->isShared()) {
            return true;
        }
        if ($this->isReference()) {
            return true;
        }
        if ($this->isShareMember()) {
            return true;
        }

        return false;
    }

    /**
     * Check if node is a sub node of a share.
     */
    public function isShareMember(): bool
    {
        if(!isset($this->resource['shared'])) {
            return false;
        }

        return $this->resource['shared'] instanceof ObjectId && !$this->isReference();
    }

    /**
     * Check if node is a sub node of an external storage mount.
     */
    public function isMountMember(): bool
    {
        if(!isset($this->resource['storage_reference'])) {
            return false;
        }

        return $this->resource['storage_reference'] instanceof ObjectId;
    }

    /**
     * Is share.
     */
    public function isShare(): bool
    {
        if(!isset($this->resource['shared'])) {
            return false;
        }

        return true === $this->resource['shared'] && !$this->isReference();
    }

    /**
     * Is share (Reference or master share).
     */
    public function isShared(): bool
    {
        if (true === ($this->resource['shared'] ?? false)) {
            return true;
        }

        return false;
    }

    public function isRoot(): bool
    {
        return $this->resource['_id'] === null;
    }

    /**
     * Get mount node.
     */
    public function getMount(): ?ObjectId
    {
        if(count($this->resource['mount'] ?? []) > 0) {
            return $this->resource['_id'];
        }

        return $this->resource['storage_reference'] ?? null;
    }

    /**
     * Is node deleted?
     */
    public function isDeleted(): bool
    {
        if(!isset($this->resource['deleted'])) {
            return false;
        }

        return $this->resource['deleted'] instanceof UTCDateTime;
    }

    /**
     * Get last modified timestamp.
     */
    public function getLastModified(): int
    {
        if ($this->changed instanceof UTCDateTime) {
            return (int) $this->changed->toDateTime()->format('U');
        }

        return 0;
    }

    /**
     * Get unique id.
     */
    /*public function getId(): ?ObjectId
    {
        return $this->id;
    }*/

    /**
     * Get as zip.
     */
    public function getZip(): void
    {
        $archive = new ZipStream($this->name.'.zip');
        $this->zip($archive, false);
        $archive->finish();
    }

    /**
     * Create zip.
     */
    public function zip(ZipStream $archive, bool $self = true, ?NodeInterface $parent = null, string $path = '', int $depth = 0): bool
    {
        if (null === $parent) {
            $parent = $this;
        }

        if ($parent instanceof Collection) {
            $children = $parent->getChildNodes();

            if (true === $self && 0 === $depth) {
                $path = $parent->getName().DIRECTORY_SEPARATOR;
            } elseif (0 === $depth) {
                $path = '';
            } elseif (0 !== $depth) {
                $path .= DIRECTORY_SEPARATOR.$parent->getName().DIRECTORY_SEPARATOR;
            }

            foreach ($children as $child) {
                $name = $path.$child->getName();

                if ($child instanceof Collection) {
                    $this->zip($archive, $self, $child, $name, ++$depth);
                } elseif ($child instanceof File) {
                    try {
                        $resource = $child->get();
                        if ($resource !== null) {
                            $archive->addFileFromStream($name, $resource);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('failed add file ['.$child->getId().'] to zip stream', [
                            'category' => get_class($this),
                            'exception' => $e,
                        ]);
                    }
                }
            }
        } elseif ($parent instanceof File) {
            $resource = $parent->get();
            if ($resource !== null) {
                $archive->addFileFromStream($parent->getName(), $resource);
            }
        }

        return true;
    }

    /**
     * Get mime type.
     */
    public function getContentType(): string
    {
        return $this->resource['mime'];
    }

    /**
     * Is reference.
     */
    public function isReference(): bool
    {
        if(!isset($this->resource['reference'])) {
            return false;
        }


        return $this->resource['reference'] instanceof ObjectId;
    }





    /**
     * Set storage adapter.
     */
    public function setStorage(StorageAdapterInterface $adapter): self
    {
        $this->storage = $adapter;

        return $this;
    }

    /**
     * Get storage adapter.
     */
    public function getStorage(): StorageAdapterInterface
    {
        return $this->storage;
    }

    /**
     * Is mount.
     */
    public function isMounted(): bool
    {
        return count($this->mount ?? []) > 0;
    }

    /**
     * Get Share name.
     */
    //TODO:WRONG
    /*public function getShareName(): string
    {
        if ($this->isShare()) {
            return $this->share_name;
        }

        return $this->fs->findRawNode($this->getShareId())['share_name'];
    }*/

    /**
     * Set collection filter.
     */
    public function setFilter(?array $filter = null)
    {
        $this->resource['filter'] = json_encode($filter, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Get collection.
     */
    public function get(): void
    {
        $this->getZip();
    }

    /**
     * Fetch children items of this collection.
     *
     * Deleted:
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     */
    /*public function getChildNodes(int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = [], ?int $offset = null, ?int $limit = null, bool $recursive = false): Generator
    {
        $filter = $this->getChildrenFilter($deleted, $filter);

        if ($recursive === false) {
            return $this->fs->findNodesByFilter($filter, $offset, $limit);
        }

        unset($filter['parent']);

        return $this->fs->findNodesByFilterRecursive($this, $filter, $offset, $limit);
    }*/

    /**
     * Fetch children items of this collection (as array).
     *
     * Deleted:
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     */
    /*public function getChildren(int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): array
    {
        return iterator_to_array($this->getChildNodes($deleted, $filter));
    }*/

    /**
     * Is custom filter node.
     */
    public function isFiltered(): bool
    {
        return !empty($this->resource['filter']);
    }

    /**
     * Get number of children.
     */
    public function getSize(): int
    {
        return 0;
        //return count($this->getChildren());
    }

    public function getFilter(): string
    {
        return $this->resource['filter'] ?? '';
    }

    /**
     * Get real id (reference).
     *
     * @return ObjectId
     */
    public function getRealId(): ?ObjectId
    {
        if ($this->isShared() && $this->isReference()) {
            return $this->resource['reference'];
        }

        return $this->resource['_id'] ?? null;
    }

    /**
     * Get user quota information.
     */
   /* public function getQuotaInfo(): array
    {
        $quota = $this->user->getQuotaUsage();

        return [
            $quota['used'],
            $quota['available'],
        ];
   }*/

    /**
     * Fetch children items of this collection.
     */
    /*public function getChild($name, int $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): NodeInterface
    {
        $name = $this->checkName($name);
        $filter = $this->getChildrenFilter($deleted, $filter);
        $filter['name'] = new Regex('^'.preg_quote($name).'$', 'i');
        $node = $this->db->storage->findOne($filter);

        if (null === $node) {
            throw new Exception\NotFound(
                'node called '.$name.' does not exists here',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $this->logger->debug('loaded node ['.$node['_id'].' from parent node ['.$this->getRealId().']', [
            'category' => get_class($this),
        ]);

        return $this->fs->initNode($node);
    }*/

    /**
     * Check if this collection has child named $name.
     *
     * deleted:
     *
     *  0 - Exclude deleted
     *  1 - Only deleted
     *  2 - Include deleted
     *
     * @param string $name
     * @param int    $deleted
     */
    /*public function childExists($name, $deleted = NodeInterface::DELETED_EXCLUDE, array $filter = []): bool
    {
        $name = $this->checkName($name);

        $find = [
            'parent' => $this->getRealId(),
            'name' => new Regex('^'.preg_quote($name).'$', 'i'),
        ];

        if (null !== $this->user) {
            $find['owner'] = $this->user->getId();
        }

        switch ($deleted) {
            case NodeInterface::DELETED_EXCLUDE:
                $find['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $find['deleted'] = ['$type' => 9];

                break;
        }

        $find = array_merge($filter, $find);

        if ($this->isSpecial()) {
            unset($find['owner']);
        }

        $node = $this->db->storage->findOne($find);

        return (bool) $node;
    }*/

    /**
     * Validate filtered collection query.
     */
    protected function validateFilter(string $filter): bool
    {
        $filter = toPHP(fromJSON($filter), [
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        ]);

        $this->db->storage->findOne($filter);

        return true;
    }
}
