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

use \Psr\Log\LoggerInterface as Logger;
use \Micro\Config;

class None implements AdapterInterface
{
    /**
     * Config
     *
     * @var Config
     */
    protected $config;


    /**
     * Initialize
     *
     * @param  Iterable $config
     * @param  Logger $logger
     * @return void
     */
    public function __construct(?Iterable $config, Logger $logger)
    {
    }


    /**
     * Get attribute sync cache
     *
     * @return int
     */
    public function getAttributeSyncCache(): int
    {
        return -1;
    }


    /**
     * Authenticate
     *
     * @return bool
     */
    public function authenticate(): bool
    {
        return true;
    }


    /**
     * Get identity
     *
     * @return string
     */
    public function getIdentity(): string
    {
        return '';
    }


    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return [];
    }
}
