<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Converter\Adapter\AdapterInterface;
use Balloon\Converter\Adapter\ImagickImage;
use Balloon\Converter\Adapter\Office;
use Balloon\Converter\Exception;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use Micro\Container\AdapterAwareInterface;
use Psr\Log\LoggerInterface;

class Converter implements AdapterAwareInterface
{
    /**
     * Default adapter.
     *
     * @var array
     */
    const DEFAULT_ADAPTER = [
        ImagickImage::class => [],
        Office::class => [],
    ];

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
    public function setOptions(? Iterable $config = null): Converter
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch($option) {
                case 'adapter':
                    foreach($value as $name => $adapter) {
                        $this->injectAdapter($name, $value);
                    }
                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
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

        $this->logger->debug('inject converter adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this)
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
    public function getAdapter(string $name)
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
     * @param File   $file
     *
     * @return Result
     */
    public function createPreview(File $file): Result
    {
        $this->logger->debug('create preview from file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        foreach ($this->adapter as $name => $adapter) {
            try {
                if ($adapter->match($file)) {
                    return $adapter->createPreview($file);
                } else {
                    $this->logger->debug('skip convert adapter ['.$name.'], adapter can not handle file', [
                        'category' => get_class($this),
                    ]);
                }
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

        foreach ($this->adapter as $name => $adapter) {
            try {
                if ($adapter->match($file)) {
                    return $adapter->convert($file, $format);
                } else {
                    $this->logger->debug('skip convert adapter ['.$name.'], adapter can not handle file', [
                        'category' => get_class($this),
                    ]);
                }
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
