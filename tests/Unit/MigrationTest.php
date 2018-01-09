<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit;

use Balloon\Migration;
use Balloon\Migration\Delta\CoreInstallation;
use Balloon\Migration\Delta\FileToStorageAdapter;
use Balloon\Migration\Delta\QueueToCappedCollection;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class MigrationTest extends Test
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
        $this->db_setup = new Migration($this->getMockDatabase(), $this->createMock(LoggerInterface::class));
    }

    /*public function testInjectDeltas()
    {
        $this->db_setup->injectAdapter(new CoreInstallation($this->getMockDatabase(), $this->createMock(LoggerInterface::class)));
        $this->db_setup->injectAdapter(new FileToStorageAdapter($this->getMockDatabase(), $this->createMock(LoggerInterface::class)));
        $this->db_setup->injectAdapter(new QueueToCappedCollection($this->getMockDatabase(), $this->createMock(LoggerInterface::class)));
    }*/

    /*
     * @depends testInitDatabase
     *
     * @param mixed $db
     */
    /*public function testInitCoreIndices($db)
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
    }*/
}
