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
use Micro\Container\AdapterAwareInterface;
use Psr\Log\LoggerInterface;
use Balloon\Filesystem\Storage\Adapter\Gridfs;

class Storage implements AdapterAwareInterface
{
    /**
     * Default adapter
     */
    const DEFAULT_ADAPTER = [
        'gridfs' => [
            'use' => Gridfs::class
        ]
    ];

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
     * Get default adapter
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
    public function injectAdapter($adapter, ?string $name=null): AdapterAwareInterface
    {
        if(!($adapter instanceof AdapterInterface)) {
            throw new Exception('adapter needs to implement AdapterInterface');
        }

        if($name === null) {
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
     * @param string $id
     *
     * @return bool
     */
    public function hasFile(File $file, array $options): bool
    {
        if ($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->hasFile($file, $options['attributes']);
        }
    }

    /**
     * Delete file.
     *
     * @param string $id
     *
     * @return bool
     */
    public function deleteFile(File $file, array $options): bool
    {
        if ($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->deleteFile($file, $options['attributes']);
        }
    }

    /**
     * Get stored file.
     *
     * @param File $file
     *
     * @return resource
     */
    public function getFile(File $file, array $options)
    {
        if ($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->getFile($file, $options['attributes']);
        }
    }

    /**
     * Store file.
     *
     * @param File     $file
     * @param resource $contents
     *
     * @return mixed
     */
    public function storeFile(File $file, array $options, $contents)
    {
        $options['attributes'] = [];
        if ($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->storeFile($file, $contents);
        }
    }

    /**
     * Validate storage options.
     *
     * @param array $options
     *
     * @return bool
     */
    protected function isValidStorageRequest(array $options): bool
    {
        if (!isset($options['adapter'])) {
            throw new Exception('no storage adapter given');
        }

        if (!isset($options['attributes'])) {
            throw new Exception('no storage adapter attributes given');
        }

        return true;
    }
}
