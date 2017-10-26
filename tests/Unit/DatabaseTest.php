<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit;

use Balloon\Database;
use Balloon\Database\DatabaseInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class DatabaseTest extends Test
{
    protected $db_setup;
    protected $expected_indices = [
        'balloon.user.username_1',
        'balloon.fs.files.md5_1',
        'balloon.storage.acl.group.group_1',
        'balloon.storage.acl.user.user_1',
        'balloon.storage.hash_1',
        'balloon.storage.parent_1_owner_1',
        'balloon.storage.reference_1',
        'balloon.storage.shared_1',
        'balloon.storage.deleted_1',
    ];

    public function setUp()
    {
        $this->db_setup = new Database($this->getMockApp(), $this->getMockDatabase(), $this->createMock(LoggerInterface::class));
    }

    public function testInitDatabase()
    {
        $this->assertInstanceOf(DatabaseInterface::class, $this->db_setup->getSetups()[0]);
    }

    /**
     * @depends testInitDatabase
     *
     * @param mixed $db
     */
    public function testInitCoreIndices($db)
    {
        $available = [];

        $this->db_setup->init();
        $mongodb = $this->getMockDatabase();
        foreach ($mongodb->listCollections() as $collection) {
            foreach ($mongodb->{$collection->getName()}->listIndexes() as $index) {
                $available[] = $index['ns'].'.'.$index['name'];
            }
        }

        $this->assertSame(sort($this->expected_indices), sort($available));
    }
}
