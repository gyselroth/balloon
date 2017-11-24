<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Filesystem;

use Balloon\Exception;
use Balloon\Exception\InvalidArgument;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;

/**
 * @coversNothing
 */
class InitNodeTest extends Test
{
    protected $fs;

    public function setUp()
    {
        $server = $this->getMockServer();
        $this->fs = $server->getFilesystem();
    }

    public function testValid()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => true,
        ];

        // execute SUT
        $node = $this->fs->initNode($nodeData);

        // assertion
        $this->assertSame($node->getRawAttributes(), $nodeData);
    }

    public function testNoDirectoryAttribute()
    {
        $this->expectException(Exception::class);
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
        ];

        // execute SUT
        $node = $this->fs->initNode($nodeData);
    }

    public function testValidParent()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => $this->fs->getRoot()->getId(),
        ];

        // execute SUT
        $node = $this->fs->initNode($nodeData);

        // assertion
        $this->assertSame($node->getRawAttributes(), $nodeData);
    }

    public function testInexistentParent()
    {
        $this->expectException(InvalidArgument::class);
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => new ObjectId(),
        ];
        // execute SUT
        $node = $this->fs->initNode($nodeData);
    }

    public function testInvalidParentId()
    {
        $this->expectException(InvalidArgument::class);
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => false,
        ];

        // execute SUT
        $node = $this->fs->initNode($nodeData);
    }
}
