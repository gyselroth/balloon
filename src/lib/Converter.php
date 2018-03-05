<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Converter\Adapter\AdapterInterface;
use Balloon\Converter\Exception;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use Psr\Log\LoggerInterface;

class Converter
{
    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Adapter.
     *
     * @var array
     */
    protected $adapter = [];

    /**
     * Initialize.
     *
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Converter
     */
    public function setOptions(? Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'adapter':
                    foreach ($value as $name => $adapter) {
                        $this->injectAdapter($adapter, $name);
                    }

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
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
     * @param AdapterInterface $adapter
     *
     * @return Converter
     */
    public function injectAdapter(AdapterInterface $adapter, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject converter adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this),
        ]);

        if ($this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is already registered');
        }

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
    public function getAdapter(string $name): AdapterInterface
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }

    /**
     * Get adapters.
     *
     * @param array $adapters
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
                throw new Exception('adapter '.$name.' is not registered');
            }
            $list[$name] = $this->adapter[$name];
        }

        return $list;
    }

    /**
     * Get supported formats.
     *
     * @return array
     */
    public function getSupportedFormats(File $file): array
    {
        foreach ($this->adapter as $adapter) {
            if ($adapter->match($file)) {
                return $adapter->getSupportedFormats($file);
            }
        }

        return [];
    }

    /**
     * Create preview.
     *
     * @param File $file
     *
     * @return Result
     */
    public function createPreview(File $file): Result
    {
        $this->logger->debug('create preview from file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        if (0 === $file->getSize()) {
            throw new Exception('can not create preview from empty file');
        }

        foreach ($this->adapter as $name => $adapter) {
            try {
                if ($adapter->matchPreview($file)) {
                    return $adapter->createPreview($file);
                }
                $this->logger->debug('skip convert adapter ['.$name.'], adapter can not handle file', [
                        'category' => get_class($this),
                    ]);
            } catch (\Exception $e) {
                $this->logger->error('failed execute adapter ['.get_class($adapter).']', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        throw new Exception('all adapter failed');
    }

    /**
     * Convert document.
     *
     * @param File   $file
     * @param string $format
     *
     * @return Result
     */
    public function convert(File $file, string $format): Result
    {
        $this->logger->debug('convert file ['.$file->getId().'] to format ['.$format.']', [
            'category' => get_class($this),
        ]);

        if (0 === $file->getSize()) {
            throw new Exception('can not convert empty file');
        }

        foreach ($this->adapter as $name => $adapter) {
            try {
                if ($adapter->match($file) && in_array($format, $adapter->getSupportedFormats($file), true)) {
                    return $adapter->convert($file, $format);
                }
                $this->logger->debug('skip convert adapter ['.$name.'], adapter can not handle file', [
                        'category' => get_class($this),
                    ]);
            } catch (\Exception $e) {
                $this->logger->error('failed execute adapter ['.get_class($adapter).']', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        throw new Exception('all adapter failed');
    }
}
