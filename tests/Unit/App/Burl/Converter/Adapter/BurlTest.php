<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Lib\Converter\Adapter;

use Balloon\App\Burl\Constructor\Http;
use Balloon\App\Burl\Converter\Adapter\Burl;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Hook;
use Balloon\Session\Factory as SessionFactory;
use Balloon\Testsuite\Unit\Test;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\Response;
use Imagick;
use MimeType\MimeType;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class BurlTest extends Test
{
    protected const SUPPORTED_FORMATS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
    ];
    protected const BURL_MIME_TYPE = 'application/vnd.balloon.burl';
    protected const PDF_MIME_TYPE = 'application/pdf';
    protected const DUMMY_IMAGE_B64 = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgBwgJYAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+f6KKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooqS3t5ru5it7eJ5Z5WCRxouWZjwAAOpoAjor2C3+FPh7wnpsOofEbX/scky7otNsjulP1OCT6HAwP71RnVPgeG8oaD4gIHHmhzg/8AkX8elAHkdFei+KNE+G8nhy51Xwp4gvBfQlD/AGberywZgpCkqOgJPBbpXnVABRWx4Z8Map4u1qLStJg82eTlmbhI17sx7Af/AFhk16de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I/75Cn9aANf4ceG0+G2g3nxB8VwtBMITHp9k/wAsjFh6dmboB2G4mvFdT1G41fVbvUrtt1zdTNNIR03McnHtzWn4o8Ya74xvlu9bvnuGQERxgBY4x/sqOB9epwMmsKgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigD/9k=';
    protected const DUMMY_PDF_B64 = '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nLOwoKV1bq1BvaU4xlvbcW17pb0/hb0gAAB1D+psevlcjmalkWZ2VZCyyqzSri1OM3p7bktx0uy1vzor0cpxWBGdWLRlW05Fu7la4wcK+mceiLW9v82+p6/VdvcpZubjf02nj8J3TphbK6dl0FGUpNKOtJvSSj8+WTZvOxzOLhxv23Tj4+ni/wAm5Qf9zk9flvv27dPjS2nnxv1D9pVGm/Eptrrxr6K+zTTsjJbffT7vu/fSS8Iixc7ExsWyfrZk8mePZR6OkqoqW1+3U20t71r313J83l8G+Wfm113rOzodNkJJKuttpzknvct6elpa6vOjQgAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//9kKZW5kc3RyZWFtCmVuZG9iagoxMiAwIG9iago2NjEKZW5kb2JqCjEzIDAgb2JqCjw8Cj4+CmVuZG9iagoxNCAwIG9iago2NjEKZW5kb2JqCjE1IDAgb2JqCjw8Cj4+CmVuZG9iagoxNiAwIG9iago2NjEKZW5kb2JqCjE3IDAgb2JqCjw8Ci9UaXRsZSAoZmZmKQovQ3JlYXRpb25EYXRlIChEOjIwMTgwODIxMDkyMTUxKQovTW9kRGF0ZSAoRDoyMDE4MDgyMTA5MjE1MSkKL1Byb2R1Y2VyIChJbWFnZU1hZ2ljayA2LjguOS05IFExNiB4ODZfNjQgMjAxOC0wNy0xMCBodHRwOi8vd3d3LmltYWdlbWFnaWNrLm9yZykKPj4KZW5kb2JqCnhyZWYKMCAxOAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMTAgMDAwMDAgbiAKMDAwMDAwMDA1OSAwMDAwMCBuIAowMDAwMDAwMTE4IDAwMDAwIG4gCjAwMDAwMDAzMDQgMDAwMDAgbiAKMDAwMDAwMDM5MCAwMDAwMCBuIAowMDAwMDAwNDA4IDAwMDAwIG4gCjAwMDAwMDA0NDYgMDAwMDAgbiAKMDAwMDAwMDQ2NyAwMDAwMCBuIAowMDAwMDA2NjM5IDAwMDAwIG4gCjAwMDAwMDY2NTkgMDAwMDAgbiAKMDAwMDAwNjY4NyAwMDAwMCBuIAowMDAwMDA3NDg3IDAwMDAwIG4gCjAwMDAwMDc1MDcgMDAwMDAgbiAKMDAwMDAwNzUyOSAwMDAwMCBuIAowMDAwMDA3NTQ5IDAwMDAwIG4gCjAwMDAwMDc1NzEgMDAwMDAgbiAKMDAwMDAwNzU5MSAwMDAwMCBuIAp0cmFpbGVyCjw8Ci9TaXplIDE4Ci9JbmZvIDE3IDAgUgovUm9vdCAxIDAgUgovSUQgWzxhYWZjMWNkMDg5MGY2MTEwZDEzZTVlZmNmN2RmNWJjZGUzMWQwY2QwZDk5YTAyZGMyZGQzZjY3MjM3ZWMxNzk4PiA8YWFmYzFjZDA4OTBmNjExMGQxM2U1ZWZjZjdkZjViY2RlMzFkMGNkMGQ5OWEwMmRjMmRkM2Y2NzIzN2VjMTc5OD5dCj4+CnN0YXJ0eHJlZgo3NzY4CiUlRU9GCg==';
    protected const DUMMY_IMAGE_WIDTH = 600;
    protected const PREVIEW_MAX_SIZE = 500;

    protected $fs;
    protected $storage;
    protected $burlConverter;

    public function setUp()
    {
        new Http();
        $this->fs = $this->getMockServer()->getFilesystem();
        $this->burlConverter = $this->createBurlConverter();
    }

    public function testConstructWithNonExistingOption()
    {
        $this->expectException(Exception::class);
        new Burl(
            new GuzzleHttpClient(),
            $this->createMock(LoggerInterface::class),
            [
                'foo' => null,
            ]
        );
    }

    public function testSetOptionsWithNonExistingOption()
    {
        $this->expectException(Exception::class);
        $this->burlConverter->setOptions([
            'foo' => null,
        ]);
    }

    public function testMatch()
    {
        // execute SUT
        $fileMatch = $this->burlConverter->match(new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id' => new ObjectId(),
                'name' => 'test.burl',
                'mime' => self::BURL_MIME_TYPE,
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->createMock(Collection::class),
            $this->createMock(SessionFactory::class),
        ));

        $fileNotMatch = $this->burlConverter->match(new File(
            [
                'owner' => $this->fs->getUser()->getId(),
                '_id' => new ObjectId(),
                'name' => 'test.txt',
                'mime' => 'text/plain',
            ],
            $this->fs,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->createMock(Collection::class),
            $this->createMock(SessionFactory::class),
        ));

        // assertions
        $this->assertTrue($fileMatch);
        $this->assertFalse($fileNotMatch);
    }

    public function testMatchForPreview()
    {
        $this->testMatch();
    }

    public function testGetSupportedFormats()
    {
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
            $this->createMock(Collection::class),
            $this->createMock(SessionFactory::class),
        ));

        // assertions
        $this->assertEquals(self::SUPPORTED_FORMATS, $actualSupportedFormats);
    }

    public function testCreatePreview()
    {
        $httpClient = $this->createMock(GuzzleHttpClient::class);
        $httpClient->method('request')
        ->willReturn(new Response(
            '200',
            [],
            base64_decode(self::DUMMY_IMAGE_B64)
        ));
        $this->burlConverter = $this->createBurlConverter($httpClient);

        $file = $this->getMockFile();
        $preview = $this->burlConverter->createPreview($file);

        $image = new Imagick();
        $tmp = tmpfile();
        $tmp_path = stream_get_meta_data($tmp)['uri'];
        stream_copy_to_stream($preview, $tmp);
        rewind($tmp);

        $image->readImage($tmp_path);

        // assertions
        $this->assertTrue($image->valid());
        $this->assertEquals(self::PREVIEW_MAX_SIZE, $image->getImageWidth());
    }

    public function testConvertToImage()
    {
        $httpClient = $this->createMock(GuzzleHttpClient::class);
        $httpClient->method('request')
        ->willReturn(new Response(
            '200',
            [],
            base64_decode(self::DUMMY_IMAGE_B64)
        ));
        $this->burlConverter = $this->createBurlConverter($httpClient);

        $file = $this->getMockFile();
        $result = $this->burlConverter->convert($file, 'jpg');
        $tmp = tmpfile();
        $tmp_path = stream_get_meta_data($tmp)['uri'];
        stream_copy_to_stream($result, $tmp);
        rewind($tmp);

        $image = new Imagick();
        $image->readImage($tmp_path);

        // assertions
        $this->assertTrue($image->valid());
        $this->assertEquals(self::DUMMY_IMAGE_WIDTH, $image->getImageWidth());
    }

    public function testConvertToPdf()
    {
        $httpClient = $this->createMock(GuzzleHttpClient::class);
        $httpClient->method('request')
        ->willReturn(new Response(
            '200',
            [],
            base64_decode(self::DUMMY_PDF_B64)
        ));
        $this->burlConverter = $this->createBurlConverter($httpClient);

        $file = $this->getMockFile();
        $result = $this->burlConverter->convert($file, 'pdf');

        $tmp = tmpfile();
        $tmp_path = stream_get_meta_data($tmp)['uri'];
        stream_copy_to_stream($result, $tmp);
        rewind($tmp);

        $image = new Imagick();

        // assertions
        $this->assertEquals(self::PDF_MIME_TYPE, \mime_content_type($tmp_path));
    }

    public function testMimeBurl()
    {
        $this->assertSame('application/vnd.balloon.burl', MimeType::getType('foo.burl'));
    }

    protected function getMockFile()
    {
        $mock = $this->createMock(File::class);
        $mock
            ->method('get')
            ->willReturn(
                fopen('data://text/plain;base64,'.base64_encode('http://example.com'), 'r')
            );

        return $mock;
    }

    protected function createBurlConverter(GuzzleHttpClient $httpClient = null)
    {
        if (null === $httpClient) {
            $httpClient = $this->createMock(GuzzleHttpClient::class);
        }

        return new Burl(
            $httpClient,
            $this->createMock(LoggerInterface::class),
            [
                'preview_max_size' => self::PREVIEW_MAX_SIZE,
            ]
        );
    }
}
