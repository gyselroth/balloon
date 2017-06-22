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

use \Balloon\Filesystem\Node\File;
use \Balloon\Preview\PreviewInterface;
use \Balloon\Preview\Exception;
use \Psr\Log\LoggerInterface as Logger;

class Preview
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
    public function __construct(Logger $logger, ?Iterable $config)
    {
        $this->logger = $logger;
        $this->addConverter($config);
    }


    /**
     * Add converter
     *
     * @param  Iterable $config
     * @return Preview
     */
    public function addConverter(Iterable $config): Preview
    {
        if ($config === null) {
            return $this;
        }
    
        foreach ($config as $converter) {
            $class = $converter->class;
            if (array_key_exists($class, $this->converter)) {
                throw new Exception('preview converter '.$class.' is already registered');
            }

            $instance = new $class($this->logger, $converter->config);

            if (!($instance instanceof PreviewInterface)) {
                throw new Exception('preview converter does not implement \Balloon\Preview\PreviewInterface');
            }

            $this->converter[$class] = $instance;
        }

        return $this;
    }


    /**
     * Return converter
     *
     * @param  string $class
     * @return PreviewInterface
     */
    public function getConverter(string $class): PreviewInterface
    {
        if (array_key_exists($class, $this->converter)) {
            throw new Exception('preview converter '.$class.' is not registered');
        }

        return $this->converter[$class];
    }


    /**
     * Create preview
     *
     * @param  File $file
     * @return string
     */
    public function create(File $file): string
    {
        foreach ($this->converter as $converter) {
            try {
                if ($converter->match($file)) {
                    return $converter->create($file);
                }
            } catch (\Exception $e) {
                $this->logger->error('failed execute preview converter['.get_class($converter).']', [
                    'category' => get_class($this),
                    'exception'=>$e
                ]);
            }
        }

        throw new Exception('no matching preview converter found');
    }
}
