<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Config;

use \Balloon\Exception;
use \Balloon\Config;

interface ConfigInterface
{
    /**
     * Load
     *
     * @param  string $config
     * @param  string $env
     * @return void
     */
    public function __construct(string $config, string $env);


    /**
     * Get entire simplexml
     *
     * @return mixed
     */
    public function getRaw();

    
    /**
     * Get from config
     *
     * @param   string $name
     * @return  mixed
     */
    public function __get(string $name);


    /**
     * Add config tree and merge it
     *
     * @param   mixed $config
     * @return  ConfigInterface
     */
    public function merge($config);


    /**
     * Get native config format as config instance
     *
     * @param   mixed $config
     * @return  Config
     */
    public function map($native=null): Config;
}
