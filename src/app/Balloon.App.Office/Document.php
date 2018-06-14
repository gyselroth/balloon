<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office;

use Balloon\Filesystem\Node\File;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class Document
{
    /**
     * Node.
     *
     * @var File
     */
    protected $node;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Get WOPI token.
     */
    public function __construct(Database $db, File $node)
    {
        $this->node = $node;
        $this->db = $db;
    }

    /**
     * Get running sessions for document.
     */
    public function getSessions(): Iterable
    {
        $result = $this->db->app_office_session->find([
            'node' => $this->node->getId(),
            'ttl' => [
                '$gte' => new UTCDateTime(),
            ],
        ]);

        return $result;
    }

    /**
     * Get node.
     */
    public function getNode(): File
    {
        return $this->node;
    }

    /**
     * Get size.
     */
    public function getSize(): int
    {
        if (0 === $this->node->getSize()) {
            return (new Template($this->node->getExtension()))->getSize();
        }

        return $this->node->getSize();
    }

    /**
     * Get readable stream.
     *
     * @return resource
     */
    public function get()
    {
        if (0 === $this->node->getSize()) {
            return (new Template($this->node->getExtension()))->get();
        }

        return $this->node->get();
    }
}
