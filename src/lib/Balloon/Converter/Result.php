<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter;

use \Balloon\Filesystem\Node\File;

class Result
{
    /**
     * Create result
     *
     * @param  string $path
     * @return void
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }


    /**
     * Get path
     *
     * @return string  
     */
    public function getPath()
    {
        return $this->path;
    }

    
    /**
     * Open stream
     *
     * @return resource
     */
    public function openStream()
    {
        return fopen($this->path, 'r');
    }


    /**
     * Get contents
     *
     * @return string
     */
    public function getContents(): string
    {
        return file_get_contents($this->path); 
    }


    /**
     * Clear
     */
}
