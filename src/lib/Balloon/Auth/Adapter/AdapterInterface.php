<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter;

use \Micro\Config;
use \Psr\Log\LoggerInterface as Logger;

interface AdapterInterface
{
    /**
     * Authenticate
     *
     * @param   Iterable $config
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(?Iterable $config, Logger $logger);


    /**
     * Get attribute sync cache
     *
     * @return int
     */
    public function getAttributeSyncCache(): int;


    /**
     * Authenticate
     *
     * @return  bool
     */
    public function authenticate(): bool;


    /**
     * Get unqiue identity
     *
     * @return string
     */
    public function getIdentity(): string;
    
    
    /**
     * Get user attributes
     *
     * @return array
     */
    public function getAttributes(): array;
}
