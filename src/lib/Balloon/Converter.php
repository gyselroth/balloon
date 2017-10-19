<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \Balloon\Converter\Exception;
use \Balloon\Converter\Result;
use \Balloon\Converter\Adapter\Imagick;
use \Balloon\Converter\Adapter\Office;
use \Balloon\Filesystem\Node\File;
use \Balloon\Converter\Adapter\AdapterInterface;
use \Psr\Log\LoggerInterface;
use \Micro\Container\AdapterAwareInterface;

class Converter implements AdapterAwareInterface
{
    /**
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Adapter
     *
     * @var array
     */
    protected $adapter = [];


    /**
     * Default adapter
     *
     * @var array
     */
    protected $default_adapter = [
        Imagick::class => [],
        Office::class => [],
    ];


    /**
     * Initialize
     *
     * @param  LoggerInterface $logger
     * @param  Iterable $config
     * @return void
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config=null)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return Converter
     */
    public function setOptions(? Iterable $config = null): Converter
    {
        if ($config === null) {
            $config = [];
        }

        //$adapter = $this->default_adapter;
        foreach ($config as $option => $value) {
            $this->injectAdapter($value);
        }

        return $this;
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
     * Inject adapter
     *
     * @param  AdapterInterface $adapter
     * @return AdapterInterface
     */
    public function injectAdapter(string $name, AdapterInterface $adapter) : AdapterInterface
    {
        if ($this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is already registered');
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
            throw new Exception('adapter '.$name.' is not registered');
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
                    throw new Exception('adapter '.$name.' is not registered');
                }
                $list[$name] = $this->adapter[$name];
            }

            return $list;
        }
    }


    /**
     * Get supported formats
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
     * Convert document
     *
     * @param  File $file
     * @param  string $format
     * @return Result
     */
    public function convert(File $file, string $format): Result
    {
        foreach ($this->adapter as $adapter) {
            try {
                if ($adapter->match($file)) {
                    return $adapter->convert($file, $format);
                }
            } catch (\Exception $e) {
                $this->logger->error('failed execute adapter ['.get_class($adapter).']', [
                    'category' => get_class($this),
                    'exception'=>$e
                ]);
            }
        }

        throw new Exception('all adapter failed');
    }
}
