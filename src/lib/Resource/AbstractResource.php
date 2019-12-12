<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Resource;

use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\UTCDateTimeInterface;

abstract class AbstractResource implements ResourceInterface
{
    /**
     * Kind.
     */
    public const KIND = 'Resource';

    /**
     * Data.
     *
     * @var array
     */
    protected $resource = [];

    /**
     * {@inheritdoc}
     */
    public function getId(): ?ObjectIdInterface
    {
        if(!isset($this->resource['_id'])) {
            return null;
        }

        return $this->resource['_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function getKind(): string
    {
        if (isset($this->resource['kind'])) {
            return $this->resource['kind'];
        }

        return static::KIND;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        if (isset($this->resource['name'])) {
            return $this->resource['name'];
        }

        return (string) $this->resource['_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(array $resource): self
    {
        unset($resource['_id']);
        unset($resource['kind']);
        unset($resource['metadata']['created']);
        unset($resource['metadata']['changed']);

        $this->resource = array_merge($this->resource, $resource);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        if (!isset($this->resource['name'])) {
            $this->resource['name'] = (string) $this->resource['_id'];
        }

        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): int
    {
        return $this->resource['metadata']['version'] ?? 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getChanged(): UTCDateTimeInterface
    {
        return $this->resource['metadata']['changed'];
    }

    /**
     * {@inheritdoc}
     */
    public function getCreated(): UTCDateTimeInterface
    {
        return $this->resource['metadata']['created'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDeleted(): ?UTCDateTimeInterface
    {
        return $this->resource['deleted'];
    }
}
