<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Api\Collection;

use Balloon\Testsuite\Test;

/**
 * @coversNothing
 */
class DownloadTest extends Test
{
    public function testCreate()
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?name='.$name);
        $this->assertSame(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);

        return $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testDownload($id)
    {
        $res = $this->request('GET', '/collection/'.$id);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/zip', $res->getHeaderLine('Content-Type'));
        $this->assertNotEmpty($res->getHeaderLine('Content-Disposition'));
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        $stream = fopen($tmp, 'w+');

        while ($contents = $res->getBody()->read(1024)) {
            fwrite($stream, $contents);
        }
        fclose($stream);
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
        $res = $this->request('GET', '/collection/'.$id.'?encode=base64');
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/zip', $res->getHeaderLine('Content-Type'));
        $this->assertNotEmpty($res->getHeaderLine('Content-Disposition'));

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        $stream = fopen($tmp, 'w+');

        while ($contents = $res->getBody()->read(1024)) {
            fwrite($stream, $contents);
        }

        fclose($stream);
        $this->assertFileExists($tmp);
        $this->assertNotEmpty(filesize($tmp));
        $decoded = base64_decode(file_get_contents($tmp), true);
        file_put_contents($tmp, $decoded);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        $this->assertSame('application/octet-stream', $mime);
        unlink($tmp);
    }
}
