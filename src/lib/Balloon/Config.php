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

use \Psr\Log\LoggerInterface as Logger;
use Balloon\Exception;
use Balloon\Config\ConfigInterface;
use \Iterator;
use \ArrayAccess;
use \Countable;

class Config implements ArrayAccess, Iterator, Countable
{
    /**
     * Store
     *
     * @var array
     */
    protected $store = [];


    /**
     * Position
     *
     * @var int
     */
    protected $position = 0;


    /**
     * Load config
     *
     * @param   string $config
     * @return  void
     */
    public function __construct($config=[])
    {
        if ($config instanceof ConfigInterface) {
            $this->store = $config->map();
        } elseif (is_array($config)) {
            $this->store = $config;
        } elseif ($config !== null) {
            throw new Exception('first param needs to be an instance of \Balloon\Config\ConfigInterface or an array');
        }
    }

    
    /**
     * Count
     *
     * @return int
     */
    public function count()
    {
        return count($this->store);
    }

    /**
     * Count
     *
     * @return int
     */
    public function children()
    {
        return $this->store;
    }


    /**
     * Get entry
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (isset($this->store[$key])) {
            return $this->store[$key];
        } else {
            throw new Exception('requested config key '.$key.' is not available');
        }
    }


    /**
     * Set offset
     *
     * @param  int $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->store[] = $value;
        } else {
            $this->store[$offset] = $value;
        }
    }


    /**
     * Count
     *
     * @param  int $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->store[$offset]);
    }


    /**
     * Unset offset
     *
     * @param  int $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->store[$offset]);
    }


    /**
     * Get value from offset
     *
     * @param  int $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->store[$offset]) ? $this->store[$offset] : null;
    }

    /**
     * Rewind
     *
     * @return void
     */
    public function rewind()
    {
        reset($this->store);
    }


    /**
     * Get current
     *
     * @return mixed
     */
    public function current()
    {
        return current($this->store);
    }


    /**
     * Get key
     *
     * @return int
     */
    public function key()
    {
        return key($this->store);
    }


    /**
     * Next
     *
     * @return void
     */
    public function next()
    {
        next($this->store);
    }


    /**
     * Valid
     *
     * @return bool
     */
    public function valid()
    {
        return key($this->store) !== null;
    }
}
