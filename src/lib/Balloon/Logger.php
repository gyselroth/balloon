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

use \Psr\Log\AbstractLogger;
use \Psr\Log\LogLevel;
use \Psr\Log\LoggerInterface;
use Balloon\Log\Adapter\AbstractAdapter;
use Balloon\Log\Adapter\AdapterInterface;
use Balloon\Config;

class Logger extends AbstractLogger implements LoggerInterface
{
    /**
     * Priorities
     */
    const PRIORITIES = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];


    /**
     * Adapters
     *
     * @var array
     */
    protected $adapter = [];


    /**
     * static context
     *
     * @var array
     */
    protected $context = [];


    /**
     * Initialize logger
     *
     * @param   Iterable $config
     * @return  void
     */
    public function __construct(?Iterable $config=null)
    {
        $this->setOptions($config);
    }


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return Logger
     */
    public function setOptions(?Iterable $config=null)
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            if (isset($value['enabled']) && $value['enabled'] === '1') {
                $this->addAdapter($option, $value['class'], $value['config']);
            }
        }
        
        return $this;
    }


    /**
     * Add datatype
     *
     * @param  string $name
     * @param  string $class
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function addAdapter(string $name, string $class, ?Iterable $config=null): AdapterInterface
    {
        if (isset($this->adapter[$name])) {
            throw new Exception\InvalidArgument('log adapter '.$name.' is already registered');
        }
            
        $adapter = new $class($config);
        if (!($adapter instanceof AdapterInterface)) {
            throw new Exception\InvalidArgument('log adapter must include AdapterInterface interface');
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
        if (!isset($this->adapter[$name])) {
            throw new Exception('log adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
    }


    /**
     * Get adapters
     *
     * @param  array $adapters
     * @return array
     */
    public function getAdapters(array $adapters=[]): array
    {
        if (empty($adapter)) {
            return $this->adapter;
        } else {
            $list = [];
            foreach ($adapter as $name) {
                if (!isset($this->adapter[$name])) {
                    throw new Exception('log adapter '.$name.' is not registered');
                }
                $list[$name] = $this->adapter[$name];
            }

            return $list;
        }
    }


    
    /**
     * Log message
     *
     * @param   string $level
     * @param   string $message
     * @param   array $context
     * @return  bool
     */
    public function log($level, $message, array $context=[]): bool
    {
        if (!array_key_exists($level, self::PRIORITIES)) {
            throw new Exception\InvalidArgument('log level '.$level.' is unkown');
        }

        foreach ($this->adapter as $adapter) {
            $prio = $adapter->getLevel();
 
            if (self::PRIORITIES[$level] <= $prio) {
                $msg = $this->_format($message, $adapter->getFormat(), $adapter->getDateFormat(), $level, $context);
                $adapter->log($level, $msg);
            }
        }

        return true;
    }
  

    /**
     *  Add static context
     *
     * @param  string $name
     * @param  string $value
     * @return Logger
     */
    public function addContext(string $name, string $value): Logger
    {
        $this->context[$name] = $value;
        return $this;
    }


    /**
     * Log message
     *
     * @param   string $message
     * @param   string $format
     * @param   string $date_format
     * @param   string $level
     * @param   array $context
     * @return  void
     */
    protected function _format(string $message, string $format, string $date_format, string $level, array $context=[]): string
    {
        $parsed = preg_replace_callback('/(\{(([a-z]\.*)+)\})/', function ($match) use ($message, $level, $date_format, $context) {
            $key = '';
            $context = array_merge($this->context, $context);
                    
            if ($sub_context = strpos($match[2], '.')) {
                $parts = explode('.', $match[2]);
                $name = $parts[0];
                $key = $parts[1];
            } else {
                $name = $match[2];
            }
            
            switch ($name) {
                case 'level':
                    return $match[0] = $level;
                    break;
                case 'date':
                    return $match[0] = date($date_format);
                    break;
                case 'message':
                    $replace = [];
                    foreach ($context as $key => $val) {
                        if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                            $replace['{' . $key . '}'] = $val;
                        } else {
                            $replace['{' . $key . '}'] = json_encode($val);
                        }
                    }

                    return $match[0] = strtr($message, $replace);
                    break;
                case 'context':
                    if ($sub_context) {
                        if (array_key_exists($key, $context)) {
                            if (!is_array($context[$key]) && (!is_object($context[$key]) || method_exists($context[$key], '__toString'))) {
                                return $match[0] = $context[$key];
                            } else {
                                return $match[0] = json_encode($context[$key]);
                            }
                        }
                    } else {
                        return $match[0] = json_encode($context);
                    }
                    break;
            }
        }, $format);
        
        return $parsed;
    }
}
