<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface;
use Balloon\Filesystem\Storage\Adapter\Gridfs;
use Micro\Container\AdapterAwareInterface;
use Psr\Log\LoggerInterface;

class Storage implements AdapterAwareInterface
{
    /**
     * Default adapter.
     */
    const DEFAULT_ADAPTER = [
        'gridfs' => [
            'use' => Gridfs::class,
        ],
    ];

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
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get default adapter.
     *
     * @return array
     */
    public function getDefaultAdapter(): array
    {
        return self::DEFAULT_ADAPTER;
    }

    /**
     * Has adapter.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return isset($this->adapter[$name]);
    }

    /**
     * Inject adapter.
     *
     * @param string           $name
     * @param AdapterInterface $adapter
     *
     * @return AdapterInterface
     */
    public function injectAdapter($adapter, ?string $name = null): AdapterAwareInterface
    {
        if (!($adapter instanceof AdapterInterface)) {
            throw new Exception('adapter needs to implement AdapterInterface');
        }

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
     *
     * @param string $name
     *
     * @return AdapterInterface
     */
    public function getAdapter(string $name)
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception('storage adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }

    /**
     * Get adapters.
     *
     * @param array $adapters
     *
     * @return array
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
     * Check if file exists.
     *
     * @param File   $file
     * @param array  $attributes
     * @param string $adapter
     *
     * @return bool
     */
    public function hasFile(File $file, array $attributes, ?string $adapter = null): bool
    {
        if (null === $adapter) {
            $adapter = 'gridfs';
        }

        return $this->getAdapter($adapter)->hasFile($file, $attributes);
    }

    /**
     * Get metadata for a file.
     *
     * @param File  $file
     * @param array $attributes
     *
     * @return array
     */
    public function getFileMeta(File $file, array $attributes, ?string $adapter = null): array
    {
        if (null === $adapter) {
            $adapter = 'gridfs';
        }

        return $this->getAdapter($adapter)->getFileMeta($file, $attributes);
    }

    /**
     * Delete file.
     *
     * @param File   $file
     * @param array  $attributes
     * @param string $adapter
     *
     * @return bool
     */
    public function deleteFile(File $file, array $attributes, ?string $adapter = null): bool
    {
        if (null === $adapter) {
            $adapter = 'gridfs';
        }

        return $this->getAdapter($adapter)->deleteFile($file, $attributes);
    }

    /**
     * Get stored file.
     *
     * @param File   $file
     * @param array  $attributes
     * @param string $adapter
     *
     * @return resource
     */
    public function getFile(File $file, array $attributes, ?string $adapter = null)
    {
        if (null === $adapter) {
            $adapter = 'gridfs';
        }

        return $this->getAdapter($adapter)->getFile($file, $attributes);
    }

    /**
     * Store file.
     *
     * @param File     $file
     * @param resource $contents
     * @param string   $adapter
     *
     * @return mixed
     */
    public function storeFile(File $file, $contents, ?string $adapter = null)
    {
        if (null === $adapter) {
            $adapter = 'gridfs';
        }

        return $this->getAdapter($adapter)->storeFile($file, $contents);
    }
}
