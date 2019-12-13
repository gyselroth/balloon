<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

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

class Collection extends AbstractNode implements CollectionInterface
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


    public function __construct(array $resource, ?CollectionInterface $parent, StorageAdapterInterface $storage)
    {
        $this->resource = $resource;
        $this->parent = $parent;
        $this->storage = $storage;
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
        return $this->resource['size'] ?? 0;
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
}
