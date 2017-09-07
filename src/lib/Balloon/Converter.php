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
            return $this;
        }

        foreach ($config as $option => $value) {
            if (!isset($value['enabled']) || $value['enabled'] === '1') {
                if(!isset($value['class'])) {
                    throw new Exception('class option is required');
                }

                if(isset($value['config'])) {
                    $config = $value['config'];
                } else {
                    $config = null;
                }

                $this->addConverter($option, $value['class'], $config);
            }
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
     * @param  string $name
     * @param  string $class
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function addConverter(string $name, string $class, ? Iterable $config = null) : AdapterInterface
    {
        if ($this->hasConverter($name)) {
            throw new Exception('converter '.$name.' is already registered');
        }
            
        $converter = new $class($this->logger, $config);
        if (!($converter instanceof AdapterInterface)) {
            throw new Exception('converter must include AdapterInterface interface');
        }

        $this->converter[$name] = $converter;
        return $converter;
    }


    /**
     * Inject converter
     *
     * @param  string $name
     * @param  AdapterInterface $adapter
     * @return AdapterInterface
     */
    public function injectConverter(string $name, AdapterInterface $adapter) : AdapterInterface
    {
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
