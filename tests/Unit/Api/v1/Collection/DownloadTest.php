<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use Balloon\Testsuite\Unit\Test;

class DownloadTest extends Test
{
    public function testCreate()
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        return $id;
    }

    /**
     * @depends testCreate
     */
    public function testDownload($id)
    {
        $res = $this->request('GET', '/collection/'.$id);
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

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $this->assertEquals('application/octet-stream', $mime);
        unlink($tmp);
    }
    

    /**
     * @depends testCreate
     */
    public function testDownloadBase64Encoded($id)
    {
        $res = $this->request('GET', '/collection/'.$id.'?encode=base64');
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
        unlink($tmp);
    }
}
