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

use Balloon\Exception;

interface AdapterInterface
{
    /**
     * Logger
     *
     * @param   string $level
     * @param   string $log
     */
    public function log(string $level, string $log): bool;

    
    /**
     * Create adapter
     *
     * @param Iterable $options
     * @return void
     */
    public function __construct(?Iterable $config=null);
    

    /**
     * Get format
     *
     * @return string
     */
    public function getFormat(): string;


    /**
     * Get date format
     */
    public function getDateFormat(): string;
    
    
    /**
     * Get level
     *
     * @return int
     */
    public function getLevel(): int;
    

    /**
     * Set options
     *
     * @param   Iterable $options
     * @return  AdapterInterface
     */
    public function setOptions(?Iterable $config=null): AdapterInterface;
}
