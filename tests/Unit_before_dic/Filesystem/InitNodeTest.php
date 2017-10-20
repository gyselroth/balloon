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

use Balloon\Filesystem;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use ReflectionMethod;

/**
 * @coversNothing
 */
class InitNodeTest extends Test
{
    public function setUp()
    {
        $server = self::setupMockServer();
        $this->fs = new Filesystem($server, $server->getLogger(), $server->getIdentity());
        // setup reflection for protected method
        $this->initNode = new ReflectionMethod(Filesystem::class, 'initNode');
        $this->initNode->setAccessible(true);
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
        $node = $this->initNode->invoke($this->fs, $nodeData);

        // assertion
        $this->assertSame($node->getRawAttributes(), $nodeData);
    }

    public function testNoDirectoryAttribute()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
        ];

        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);
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
        $node = $this->initNode->invoke($this->fs, $nodeData);

        // assertion
        $this->assertSame($node->getRawAttributes(), $nodeData);
    }

    public function testInexistentParent()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => new ObjectId(),
        ];
        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);
    }

    public function testInvalidParentId()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => false,
        ];

        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);
    }
}
