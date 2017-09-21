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
use \Psr\Log\LoggerInterface as Logger;

class Converter
{
    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    
    /**
     * Converter
     *
     * @var array
     */
    protected $converter = [];


    /**
     * Default converter
     *
     * @var array
     */
    protected $default_converter = [
        Imagick::class => [],
        Office::class => [],
    ];


    /**
     * Initialize
     *
     * @param  Logger $logger
     * @param  Iterable $config
     * @return void
     */
    public function __construct(Logger $logger, ?Iterable $config=null)
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
        
        $converter = $this->default_converter;

        foreach ($config as $option => $value) {
            if (!isset($value['class'])) {
                throw new Exception('option class is required');
            }

            $converter[$value['class']] = [];
            if (isset($value['config'])) {
                $config = $value['config'];
            } else {
                $config = null;
            }
            
            $converter[$value['class']] = $config;
        }

        foreach ($converter as $converter => $config) {
            $this->addConverter($converter, $config);
        }
        
        return $this;
    }


    /**
     * Has converter
     *
     * @param  string $name
     * @return bool
     */
    public function hasConverter(string $name): bool
    {
        return isset($this->converter[$name]);
    }


    /**
     * Add converter
     *
     * @param  string $class
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function addConverter(string $class, ? Iterable $config = null) : AdapterInterface
    {
        if ($this->hasConverter($class)) {
            throw new Exception('converter '.$class.' is already registered');
        }
            
        $converter = new $class($this->logger, $config);
        if (!($converter instanceof AdapterInterface)) {
            throw new Exception('converter must include AdapterInterface interface');
        }

        $this->converter[$class] = $converter;
        return $converter;
    }


    /**
     * Inject converter
     *
     * @param  AdapterInterface $adapter
     * @return AdapterInterface
     */
    public function injectConverter(AdapterInterface $adapter) : AdapterInterface
    {
        $name = get_class($adapter);

        if ($this->hasConverter($name)) {
            throw new Exception('converter '.$name.' is already registered');
        }
            
        $this->converter[$name] = $converter;
        return $converter;
    }


    /**
     * Get converter
     *
     * @param  string $name
     * @return AdapterInterface
     */
    public function getConverter(string $name): AdapterInterface
    {
        if (!$this->hasConverter($name)) {
            throw new Exception('converter '.$name.' is not registered');
        }

        return $this->converter[$name];
    }


    /**
     * Get converters
     *
     * @param  array $converters
     * @return array
     */
    public function getConverters(array $converters = []): array
    {
        if (empty($converter)) {
            return $this->converter;
        } else {
            $list = [];
            foreach ($converter as $name) {
                if (!$this->hasConverter($name)) {
                    throw new Exception('converter '.$name.' is not registered');
                }
                $list[$name] = $this->converter[$name];
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
        foreach ($this->converter as $converter) {
            if ($converter->match($file)) {
                return $converter->getSupportedFormats($file);
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
        foreach ($this->converter as $converter) {
            try {
                if ($converter->match($file)) {
                    return $converter->convert($file, $format);
                }
            } catch (\Exception $e) {
                $this->logger->error('failed execute converter ['.get_class($converter).']', [
                    'category' => get_class($this),
                    'exception'=>$e
                ]);
            }
        }

        throw new Exception('all converter failed');
    }
}
