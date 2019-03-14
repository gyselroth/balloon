<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Converter\Adapter\AdapterInterface;
use Balloon\Converter\Exception;
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
     */
    public function createPreview(File $file)
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
     */
    public function convert(File $file, string $format)
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
