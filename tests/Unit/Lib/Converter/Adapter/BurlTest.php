<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Lib\Converter\Adapter;

use Balloon\Converter\Adapter\Burl;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use Balloon\Hook;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Storage;
use Balloon\Filesystem\Storage\Adapter\Gridfs;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use \Imagick;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class BurlTest extends Test
{
    protected const EXCEPTION_MESSAGE = 'browserlessUrl option must be a valid url to a browserless instance';
    protected const SUPPORTED_FORMATS = [
        'pdf'
    ];
    protected const BURL_MIME_TYPE = 'application/vnd.balloon.burl';
    protected const DUMMY_IMAGE_B64 = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAlgDIAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+f6KKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKfCiSTxpJKIkZgGkYEhBnkkDk49qZRQB1On6F4e1m9i0vT9avl1GdxHbtdWSRwSSHhVLCVmTJwAcHnqB2z9L0OO4tby/1K5ey0+zdYpJEi8yR5WztjRSVBbCsTkgAD6A3fC0CaVJH4pv1H2Wxl3Wsbf8vVyvKIPVVO1mPQDjqwpLmSSX4dW0hbcW1i4aZj1JMUW3P/AI/QBXvNFsZdJm1TRb6a5gtnVbmG5hEUsQbhW4Zgyk8ZByCQCOQTPJoWkaXFBHrmpXkN9PEkxt7S0WXyEcBk3lpF+YqQ20DgEc54DfDP/IL8Ub93k/2V8+OmftEG3P8AwLbS+Pt3/Ce63nG37U2zHTZ/D/47igDN1rSH0e9WEzR3EE0Sz29xHnbLE33WAPI7gg8ggjtWdXReIP8AkXPCu/d5v2CXGf8Ann9pm2/rvp3hRl1K4Ph65tWntb1sh41HmWrgf64E4+UAfMCQCo5IwCADm6ltrae8uY7a2hkmnlYLHHGpZmJ6AAda6fxNbx+GrVdDsVWaK5RZptTCgi8HUCI9olP4lh82MBRyscskL743ZGwRlTg4IwR+IJFAHQa34XTRdEtb06lDc3ElzJazwwLuSF0VGwJM4c4cZwMAg4JqPS9H0yXQbjVtVvry3iS6S2jS1tVlLsVZiTukTAAUevWpp/8Akmmn/wDYYuf/AETBUOn+ILOz8Otpdzo0N8/2o3CvPO6oMqFwVQqxPBwd4HPQ0AUtQg0rzIU0e5v7pnJDrc2ixEHjG3bI+7PPp26541rnQdB0y8bTNT1q6i1GNtk5gs1kggk7qW8wM2DwSF4ION3fS0a00u71Tw3rdpaiyQ61Da3Vr5jPGPmRg6FiWwRuBBJxgc84HIal5v8Aat55/wDrvPff/vbjn9aAHarplxo+p3Gn3QTzoWwSjblYYyGU9wQQQfQ1TrovGX/IUsd27zv7KsfN3dc/Z48f+O7a52gAooooAKKKKACiiigAooooAKKKKACp7GS2hvoJby3a5tkkDSwLJ5ZkUHld2Dtz0zioKKAOv1fxH4Z1q8E9xoWsRoiiOGCHV4ligQdERfs3A6+5JJJJJNZOma1bWdve6fd2T3WlXbrIYRMFljdc7HR9pAYBmB+XBBPHTGNRQBuXms2MekzaZo1jNbQXDq9zNcTiWWULyq8KoVQTnGDkgEngYnk1zSdTht31rTLua9giSHz7S7WLzkQBV3ho2+YKANw6gDIzzXOUUAaGs6s+sXqzGFLeCKNYbe3jJKwxqMKoJ5PcknqST3pLfV7m00q5sLcRxJdEefKo/eOg6Rlv7mecDqcZzgYoUUAX01i6XRpNJfZNaM4kjEgyYHzy0Z/hyOCOh9MgEUKKKANKTVt/hq30fyMeTeS3Xm7+u9I124x28vOc9/ajT7jRkt2j1LT7uaTflZba7WIgY6FWjYH8MdT1rNooA2tV1yG4srTTtMs3srC1kaZVebzJZJWCgu7gKM4VQAAAMepJN2617QtTvG1PU9EuZdRkbfcCC8EUE792K7Cy5PJCsM5ONtcxRQBb1TUrjV9TuL+6K+dM24hBhVHQKo7KAAAOwAqpRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAf/Z';

    protected $fs;
    protected $storage;
    protected $burlConverter;

    public function setUp()
    {
        $this->storage = $this->createMock(Storage::class);
        $this->storage
            ->method('getFile')
            ->willReturn(
                fopen('data://text/plain;base64,' . base64_encode('http://example.com'),'r')
            );
        $httpClient = $this->createMock(GuzzleHttpClient::class);
        $httpClient
            ->method('request')
            ->willReturn(new Response(
                '200',
                [],
                base64_decode(self::DUMMY_IMAGE_B64)
            ));

        $this->fs = $this->getMockServer()->getFilesystem();
        $this->burlConverter = new Burl(
            $httpClient,
            $this->createMock(LoggerInterface::class),
            [
                'browserlessUrl' => 'http://172.17.0.2:3000'
            ]
        );
    }

    public function testConstructWithInvalidUrlNull() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(self::EXCEPTION_MESSAGE);
        new Burl(
            new GuzzleHttpClient(),
            $this->createMock(LoggerInterface::class),
            [
                'browserlessUrl' => null,
            ]
        );
    }

    public function testConstructWithInvalidUrlSomeString() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(self::EXCEPTION_MESSAGE);
        new Burl(
            new GuzzleHttpClient(),
            $this->createMock(LoggerInterface::class),
            [
                'browserlessUrl' => 'foo',
            ]
        );
    }

    public function testSetOptionsWithInvalidUrlNull() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(self::EXCEPTION_MESSAGE);
        $this->burlConverter->setOptions([
            'browserlessUrl' => null,
        ]);
    }

    public function testSetOptionsWithInvalidUrlSomeString() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(self::EXCEPTION_MESSAGE);
        $this->burlConverter->setOptions([
            'browserlessUrl' => 'foo',
        ]);
    }

    public function testMatch() {
        // execute SUT
        $fileMatch = $this->burlConverter->match(new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id'   => new ObjectId(),
                'mime'  => self::BURL_MIME_TYPE,
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->createMock(Storage::class)
        ));

        $fileNotMatch = $this->burlConverter->match(new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id'   => new ObjectId(),
                'mime'  => 'application/xml',
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->createMock(Storage::class)
        ));

        // assertions
        $this->assertTrue($fileMatch);
        $this->assertFalse($fileNotMatch);
    }

    public function testMatchForPreview() {
        $this->testMatch();
    }

    public function testGetSupportedFormats() {
        // execute SUT
        $actualSupportedFormats = $this->burlConverter->getSupportedFormats(new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id' => new ObjectId(),
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->createMock(Storage::class)
        ));

        // assertions
        $this->assertEquals(self::SUPPORTED_FORMATS, $actualSupportedFormats);
    }

    public function testCreatePreview() {
        $file = new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id' => new ObjectId(),
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->storage
        );

        $preview = $this->burlConverter->createPreview($file);

        $image = new Imagick();
        $image->readImage($preview->getPath());
        $this->assertTrue($image->valid());
    }

    public function testConvert() {
        $file = new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id' => new ObjectId(),
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->storage
        );

        $preview = $this->burlConverter->createPreview($file);
        $image = new Imagick();
        $image->readImage($preview->getPath());
        $this->assertTrue($image->valid());
    }
}
