<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office;

use \Balloon\Filesystem\Node\File;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\Database;

class Document
{
    /**
     * Node
     *
     * @var File
     */
    protected $node;
 
    
    /**
     * Database
     *
     * @var Database
     */
    protected $db;


    /**
     * Get WOPI token
     *
     * @param   Database $db
     * @param   File $node
     * @return  void
     */
    public function __construct(Database $db, File $node)
    {
        $this->node = $node;
        $this->db   = $db;
    }


    /**
     * Get running sessions for document
     *
     * @return Iterable
     */
    public function getSessions(): Iterable
    {
        $result = $this->db->app_office_session->find([
            'node'  => $this->node->getId(),
            'ttl' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime()
            ]
        ]);

        return $result;
    }


    /**
     * Get node
     *
     * @return File
     */
    public function getNode(): File
    {
        return $this->node;
    }


    /**
     * Get size
     *
     * @return int
     */
    public function getSize(): int
    {
        if ($this->node->getSize() === 0) {
            return (new Template($this->node->getExtension()))->getSize();
        } else {
            return $this->node->getSize();
        }
    }

        
    /**
     * Get readable stream
     *
     * @return resource
     */
    public function get()
    {
        if ($this->node->getSize() === 0) {
            return (new Template($this->node->getExtension()))->get();
        } else {
            return $this->node->get();
        }
    }
}
