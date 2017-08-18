<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Api\v1\Collection;
use \Closure;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class DownloadTest extends Test
{
    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer();
        self::$controller = new Collection($server, $server->getLogger());
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf('\Micro\Http\Response', $res);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        return (string)$id;
    }

    /**
     * @depends testCreate
     */
    public function testDownload($id)
    {
        ob_start();
        $res = self::$controller->get($id);
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

        $this->assertEquals('application/octet-stream', $mime);
        unlink($tmp);
    }
    

    /**
     * @depends testCreate
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
