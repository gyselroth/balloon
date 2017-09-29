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

class Initialize
{
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }


    public function init()
    {
/*
db.fs.files.ensureIndex( { "md5": 1 }, { unique: true } )
db.storage.ensureIndex( { "share.token": 1 }, { unique: true, sparse: true } )
db.storage.ensureIndex( { "acl.group.group": 1 }, { sparse: true } )
db.storage.ensureIndex( { "acl.user.user": 1 }, { sparse: true } )
db.storage.ensureIndex( { "hash": 1, "thumbnail": 1 }, { sparse: true })
db.storage.ensureIndex( { "parent": 1 }, {"owner": 1 }, { sparse: true })
db.storage.ensureIndex({"reference": 1})
db.storage.ensureIndex({"shared": 1})
db.user.ensureIndex( { "username": 1 }, { unique: true } )
db.fs.files.dropIndex({"filename": 1})
db.fs.files.dropIndex({"filename": 1,"uploadDate":1})
db.delta.createIndex({"owner": 1})
db.delta.createIndex({"timestamp": 1})
db.delta.createIndex({"node": 1})
*/

        $this->db->createCollection('user');
        $this->db->user->createIndex('username', ['unique' => true]);

        $this->db->createCollection('fs.files');
        $this->db->selectCollection('fs.files')->createIndexes([
            [ 'key' => ['md5', ['unique' => true]]],
        ]);

        $this->db->createCollection('fs.chunks');

        $this->db->createCollection('storage');
        $this->db->createIndexes([
            [ 'key' => [ 'acl.group.group' => 1 ] ],
            [ 'key' => [ 'acl.user.user' => 1 ] ],
            [ 'key' => [ 'hash' => 1 ] ],
            [ 'key' => [ 'parent' => 1 ], ['sparse' => true] ],
            [ 'key' => [ 'owner' => 1 ], ['sparse' => true] ],
            [ 'key' => [ 'reference' => 1 ] ],
            [ 'key' => [ 'shared' => 1 ] ],
        ]);

        $this->db->createCollection('queue', [
            'capped' => true,
            'max' => 100000]
        );
    }
}
