<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use \Balloon\Filesystem\Exception;
use \Psr\Log\LoggerInterface;
use \Balloon\Filesystem\Storage\Adapter\AdapterInterface;
use \Balloon\Filesystem\Node\File;

class Storage
{
    /**
     * Storage adapter
     *
     * @var array
     */
    protected $adapter = [];


    /**
     * Storage handler
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Has adapter
     *
     * @param  string $name
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return isset($this->adapter[$name]);
    }


    /**
     * Add adapter
     *
     * @param  string $name
     * @param  string $class
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function addAdapter(string $name, string $class, ? Iterable $config = null) : AdapterInterface
    {
        if ($this->hasAdapter($name)) {
            throw new Exception('storage adapter '.$name.' is already registered');
        }

        $adapter = new $class($this->logger, $config);
        if (!($adapter instanceof AdapterInterface)) {
            throw new Exception('storage adapter must include AdapterInterface interface');
        }

        $this->adapter[$name] = $adapter;
        return $adapter;
    }


    /**
     * Inject adapter
     *
     * @param  string $name
     * @param  AdapterInterface $adapter
     * @return AdapterInterface
     */
    public function injectAdapter(string $name, AdapterInterface $adapter) : AdapterInterface
    {
        if ($this->hasAdapter($name)) {
            throw new Exception('storage adapter '.$name.' is already registered');
        }

        $this->adapter[$name] = $adapter;
        return $adapter;
    }


    /**
     * Get adapter
     *
     * @param  string $name
     * @return AdapterInterface
     */
    public function getAdapter(string $name): AdapterInterface
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception('storage adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }


    /**
     * Get adapters
     *
     * @param  array $adapters
     * @return array
     */
    public function getAdapters(array $adapters = []): array
    {
        if (empty($adapter)) {
            return $this->adapter;
        } else {
            $list = [];
            foreach ($adapter as $name) {
                if (!$this->hasAdapter($name)) {
                    throw new Exception('storage adapter '.$name.' is not registered');
                }
                $list[$name] = $this->adapter[$name];
            }

            return $list;
        }
    }


    /**
     * Validate storage options
     *
     * @param  array $options
     * @return bool
     */
    protected function isValidStorageRequest(array $options): bool
    {
        if(!isset($options['adapter'])) {
            throw new Exception('no storage adapter given');
        }

        if(!isset($options['attributes'])) {
            throw new Exception('no storage adapter attributes given');
        }

        return true;
    }


    /**
     * Check if file exists
     *
     * @param   string $id
     * @return  bool
     */
    public function hasFile(File $file, array $options): bool
    {
        if($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->hasFile($file, $options['attributes']);
        }
    }


    /**
     * Delete file
     *
     * @param   string $id
     * @return  bool
     */
    public function deleteFile(File $file, array $options): bool
    {
        if($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->deleteFile($file, $options['attributes']);
        }
    }


    /**
     * Get stored file
     *
     * @param  File $file
     * @return resource
     */
    public function getFile(File $file, array $options)
    {
        if($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->getFile($file, $options['attributes']);
        }
    }


    /**
     * Store file
     *
     * @param   File $file
     * @param   resource $contents
     * @return  mixed
     */
    public function storeFile(File $file, array $options, $contents)
    {
        $options['attributes'] = [];
        if($this->isValidStorageRequest($options)) {
            return $this->getAdapter($options['adapter'])->storeFile($file, $contents);
        }
    }
}
