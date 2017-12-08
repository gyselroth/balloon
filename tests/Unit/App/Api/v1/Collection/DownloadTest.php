<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\v1\Collection;

use Balloon\Api\v1\Collection;
use Balloon\Testsuite\Unit\App\Api\v1\Test;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Acl;
use Closure;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class DownloadTest extends Test
{
    public function setUp()
    {
        $server = $this->getMockServer();
        $this->controller = new Collection($server, new AttributeDecorator($server, $this->createMock(Acl::class)), $this->createMock(LoggerInterface::class));
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = $this->controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectId($res->getBody());
        $this->assertInstanceOf(ObjectId::class, $id);

        return (string) $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testDownload($id)
    {
        ob_start();
        $res = $this->controller->get($id);
        $this->assertInstanceOf(Response::class, $res);
        $body = $res->getBody();
        $this->assertInstanceOf(Closure::class, $body);

        $body();
        $contents = ob_get_contents();
        ob_end_clean();
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        file_put_contents($tmp, $contents);
        $this->assertFileExists($tmp);
        $this->assertNotEmpty(filesize($tmp));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $this->assertSame('application/octet-stream', $mime);
        unlink($tmp);
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testDownloadBase64Encoded($id)
    {
        /*$res = $this->request('GET', '/collection/'.$id.'?encode=base64');
        $this->assertInstanceOf('\Micro\Http\Response', $res)
        $this->assertEquals(200, $res->getStatusCode());
        $this->assertEquals('application/zip', $res->getHeaderLine('Content-Type'));
        $this->assertNotEmpty($res->getHeaderLine('Content-Disposition'));

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        $stream = fopen($tmp, 'w+');

        while($contents = $res->getBody()->read(1024)) {
            fwrite($stream, $contents);
        }

        fclose($stream);
        $this->assertFileExists($tmp);
        $this->assertNotEmpty(filesize($tmp));
        $decoded = base64_decode(file_get_contents($tmp));
        file_put_contents($tmp, $decoded);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        $this->assertEquals('application/octet-stream', $mime);
        unlink($tmp);*/
    }
}
