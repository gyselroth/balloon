<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database;

use \MongoDB\Database;
use \Psr\Log\LoggerInterface;

interface DeltaInterface
{
    /**
     * Construct
     *
     * @param Database $db
     * @param LoggerInterface $logger
     */
    public function __construct(Database $db, LoggerInterface $logger);


    /**
     * Get collection
     *
     * @return string
     */
    public function getCollection(): string;


    /**
     * Upgrade object
     *
     * @param  array $object
     * @return array
     */
    public function upgradeObject(array $object): array;


    /**
     * Pre objects
     *
     * @return bool
     */
    public function preObjects(): bool;


    /**
     * Post objects
     *
     * @return bool
     */
    public function postObjects(): bool;
}
