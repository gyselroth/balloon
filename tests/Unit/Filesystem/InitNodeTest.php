<?php
namespace Balloon\Testsuite\Unit\Filesystem;

use \Balloon\Filesystem;
use \Balloon\Filesystem\Node\NodeInterface;
use \Balloon\Testsuite\Unit\Test;
use \MongoDB\BSON\ObjectId;
use \ReflectionMethod;

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
            'directory' => true
        ];

        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);

        // assertion
        $this->assertEquals($node->getRawAttributes(), $nodeData);
    }

    /**
     * @expectedException \Balloon\Exception
     * @expectedExceptionMessage directory attribute does not exists
     */
    public function testNoDirectoryAttribute()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId()
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
            'parent' => $this->fs->getRoot()->getId()
        ];

        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);

        // assertion
        $this->assertEquals($node->getRawAttributes(), $nodeData);
    }

    /**
     * @expectedException \Balloon\Exception\InvalidArgument
     * @expectedExceptionMessage invalid parent node specified
     */
    public function testInexistentParent()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => new ObjectId()
        ];
        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);
    }

    /**
     * @expectedException \Balloon\Exception\InvalidArgument
     * @expectedExceptionMessage invalid parent node specified
     */
    public function testInvalidParentId()
    {
        // fixture
        $nodeData = [
            'owner' => $this->fs->getUser()->getId(),
            '_id' => new ObjectId(),
            'directory' => false,
            'parent' => false
        ];

        // execute SUT
        $node = $this->initNode->invoke($this->fs, $nodeData);
    }
}
