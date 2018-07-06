<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class Storage
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Storage adapter.
     *
     * @var array
     */
    protected $adapter = [];

    /**
     * Storage handler.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Has adapter.
     */
    public function hasAdapter(string $name): bool
    {
        return isset($this->adapter[$name]);
    }

    /**
     * Inject adapter.
     *
     * @param string $name
     *
     * @return Storage
     */
    public function injectAdapter(AdapterInterface $adapter, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($adapter);
        }

        if ($this->hasAdapter($name)) {
            throw new Exception('storage adapter '.$name.' is already registered');
        }

        $this->logger->debug('inject storage adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this),
        ]);

        $this->adapter[$name] = $adapter;

        return $this;
    }

    /**
     * Get adapter.
     */
    public function getAdapter(string $name): AdapterInterface
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception('storage adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }

    /**
     * Get adapters.
     *
     *
     * @return AdapterInterface[]
     */
    public function getAdapters(array $adapters = []): array
    {
        if (empty($adapter)) {
            return $this->adapter;
        }
        $list = [];
        foreach ($adapter as $name) {
            if (!$this->hasAdapter($name)) {
                throw new Exception('storage adapter '.$name.' is not registered');
            }
            $list[$name] = $this->adapter[$name];
        }

        return $list;
    }

    /**
     * Check if node exists.
     *
     * @param array  $attributes
     * @param string $adapter
     */
    public function hasNode(NodeInterface $node, ?array $attributes = null, ?string $adapter = null): bool
    {
        return $this->execAdapter('hasNode', $node, $attributes, $adapter);
    }

    /**
     * Delete file.
     *
     * @param array  $attributes
     * @param string $adapter
     */
    public function deleteFile(File $file, ?array $attributes = null, ?string $adapter = null): bool
    {
        return $this->execAdapter('deleteFile', $file, $attributes, $adapter);
    }

    /**
     * Get stored file.
     *
     * @param array  $attributes
     * @param string $adapter
     *
     * @return resource
     */
    public function getFile(File $file, ?array $attributes = null, ?string $adapter = null)
    {
        return $this->execAdapter('getFile', $file, $attributes, $adapter);
    }

    /**
     * Store file.
     */
    public function storeFile(File $file, ObjectId $session, ?string &$adapter = null)
    {
        $attrs = $file->getAttributes();

        if ($attrs['storage_adapter']) {
            $adapter = $attrs['storage_adapter'];
        } elseif (null === $adapter) {
            $adapter = 'gridfs';
        }

        return $this->getAdapter($adapter)->storeFile($file, $session);
    }

    /**
     * Store temporary.
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session = null)
    {
        $adapter = 'gridfs';

        return $this->getAdapter($adapter)->storeTemporaryFile($stream, $user, $session);
    }

    /**
     * Execute command on adapter.
     *
     * @param array  $attributes
     * @param string $adapter
     */
    protected function execAdapter(string $method, File $file, ?array $attributes = null, ?string $adapter = null)
    {
        $attrs = $file->getAttributes();

        if ($attrs['storage_adapter']) {
            $adapter = $attrs['storage_adapter'];
        } elseif (null === $adapter) {
            $adapter = 'gridfs';
        }

        if ($attributes === null) {
            $attributes = $attrs['storage'];
        }

        return $this->getAdapter($adapter)->{$method}($file, $attributes);
    }
}
