<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Log\Adapter;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * Log format
     *
     * @var string
     */
    protected $format = '{date} {message}';


    /**
     * Date format
     *
     * @var string
     */
    protected $date_format = 'U';


    /**
     * Level
     *
     * @var int
     */
    protected $level = 7;

    
    /**
     * Logger
     *
     * @param   string $level
     * @param   string $log
     */
    abstract public function log(string $level, string $log): bool;

    
    /**
     * Create adapter
     *
     * @param Iterable $options
     * @return void
     */
    public function __construct(?Iterable $config=null)
    {
        $this->setOptions($config);
    }


    /**
     * Get format
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }


    /**
     * Get date format
     */
    public function getDateFormat(): string
    {
        return $this->date_format;
    }
   
 
    /**
     * Get level
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }
    

    /**
     * Set options
     *
     * @param   Iterable $options
     * @return  AdapterInterface
     */
    public function setOptions(?Iterable $config=null): AdapterInterface
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $attr => $val) {
            switch ($attr) {
                case 'format':
                    $this->format = (string)$val;
                break;
               
                case 'level':
                    if (!is_numeric($val)) {
                        throw new Exception\InvalidArgument('log level must be a number');
                    }

                    $this->level = (int)$val;
                break;
                
                case 'date_format':
                    $this->date_format = (string)$val;
                break;
            }
        }

        return $this;
    }
}
