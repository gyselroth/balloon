<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1\AttributeDecorator;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Closure;
use MongoDB\BSON\UTCDateTime;
use stdClass;

class NodeDecorator
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $custom = [];

    /**
     * Init.
     */
    public function __construct(Server $server, Acl $acl)
    {
        $this->server = $server;
        $this->acl = $acl;
    }

    /**
     * Decorate attributes.
     */
    public function decorate(NodeInterface $node, array $attributes = []): array
    {
        $requested = $this->parseAttributes($attributes);
        $attributes = $node->getAttributes();

        $attrs = array_merge(
            $this->getAttributes($node, $attributes),
            $this->getTimeAttributes($node, $attributes),
            $this->getTypeAttributes($node, $attributes),
            $this->custom
        );

        if (0 === count($requested)) {
            return $this->translateAttributes($node, $attrs);
        }

        return $this->translateAttributes($node, array_intersect_key($attrs, array_flip($requested)));
    }

    /**
     * Add decorator.
     *
     * @return AttributeDecorator
     */
    public function addDecorator(string $attribute, Closure $decorator): self
    {
        $this->custom[$attribute] = $decorator;

        return $this;
    }

    /**
     * Parse v1 attribute filter requests.
     *
     * @param array
     */
    protected function parseAttributes(array $attributes): array
    {
        foreach ($attributes as &$attribute) {
            $parts = explode('.', $attribute);
            $attribute = $parts[0];
        }

        return $attributes;
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(NodeInterface $node, array $attributes): array
    {
        $acl = $this->acl;
        $server = $this->server;
        $fs = $this->server->getFilesystem();

        return [
            'id' => (string) $attributes['_id'],
            'name' => (string) $attributes['name'],
            'mime' => (string) $attributes['mime'],
            'readonly' => (bool) $attributes['readonly'],
            'directory' => $node instanceof Collection,
            'meta' => function ($node) {
                return (object) $node->getMetaAttributes();
            },
            'size' => function ($node) {
                return $node->getSize();
            },
            'path' => function ($node) {
                try {
                    return $node->getPath();
                } catch (\Exception $e) {
                    return null;
                }
            },
            'parent' => function ($node) {
                $parent = $node->getParent();

                if (null === $parent || $parent->isRoot()) {
                    return null;
                }

                return (string) $parent->getId();
            },
            'access' => function ($node) use ($acl) {
                return $acl->getAclPrivilege($node);
            },
            'share' => function ($node) {
                if (!$node->isShared() && !$node->isSpecial()) {
                    return false;
                }

                try {
                    return $node->getShareNode()->getShareName();
                } catch (\Exception $e) {
                    return false;
                }
            },
            'shareowner' => function ($node) use ($server, $fs) {
                if (!$node->isSpecial()) {
                    return null;
                }

                try {
                    return $server->getUserById($fs->findRawNode($node->getShareId())['owner'])->getUsername();
                } catch (\Exception $e) {
                    return null;
                }
            },
        ];
    }

    /**
     * Convert UTCDateTime to unix ts.
     *
     * @param UTCDateTime $date
     *
     * @return stdClass
     */
    protected function dateTimeToUnix(?UTCDateTime $date): ?stdClass
    {
        if (null === $date) {
            return null;
        }

        $date = $date->toDateTime();
        $ts = new stdClass();
        $ts->sec = $date->format('U');
        $ts->usec = $date->format('u');

        return $ts;
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     */
    protected function getTimeAttributes(NodeInterface $node, array $attributes): array
    {
        return [
            'created' => function ($node) use ($attributes) {
                return $this->dateTimeToUnix($attributes['created']);
            },
            'changed' => function ($node) use ($attributes) {
                return $this->dateTimeToUnix($attributes['changed']);
            },
            'deleted' => function ($node) use ($attributes) {
                if (false === $attributes['deleted']) {
                    return false;
                }

                return $this->dateTimeToUnix($attributes['deleted']);
            },
            'destroy' => function ($node) use ($attributes) {
                if (null === $attributes['destroy']) {
                    return null;
                }

                return $this->dateTimeToUnix($attributes['destroy']);
            },
        ];
    }

    /**
     * Get Attributes.
     *
     * @param NodeInterface
     */
    protected function getTypeAttributes(NodeInterface $node, array $attributes): array
    {
        $server = $this->server;
        $fs = $this->server->getFilesystem();

        if ($node instanceof File) {
            return [
                'version' => $attributes['version'],
                'hash' => $attributes['hash'],
            ];
        }

        return [
            'shared' => $node->isShared(),
            'reference' => $node->isReference(),
            'filtered' => $node->isFiltered(),
        ];
    }

    /**
     * Execute closures.
     *
     * @param NodeInterface
     */
    protected function translateAttributes(NodeInterface $node, array $attributes): array
    {
        foreach ($attributes as $key => &$value) {
            if ($value instanceof Closure) {
                $result = $value->call($this, $node);
                if ($result === null && $key !== 'destroy') {
                    unset($attributes[$key]);
                } else {
                    $value = $result;
                }
            }
        }

        return $attributes;
    }
}
