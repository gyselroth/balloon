<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\DesktopClient\Api\v1;

use Balloon\App\DesktopClient\DesktopClient;
use Balloon\Api\Controller;
use Balloon\App\DesktopClient\App\Http As App;
use Balloon\App\Convert\Exception;
use Balloon\App\Convert\Job;
use Balloon\Async;
use Balloon\Converter;
use Balloon\Server;
use Micro\Http\Response;
use Balloon\Filesystem\Node\File;

class Download
{
    /**
     * Desktop client
     *
     * @var DesktopClient
     */
    protected $client;


    /**
     * @param DesktopClient $client
     */
    public function __construct(DesktopClient $client)
    {
        $this->client = $client;
    }

    /**
     * @api {get} /api/v1/desktop-client
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup App\DesktopClient
     * @apiPermission none
     * @apiDescription Download balloon desktop client
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v1/desktop-client?format=exe > balloon-desktop.exe"
     *
     * @apiParam (GET Parameter) {string} [format] Request client foramt (deb, rpm, exe, dmg, linux_zip or a custom format)
     *
     * @apiSuccessExample {binary} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param string $format
     * @return Response
     */
    public function get(string $format): Response
    {
        $url = $this->client->getUrl($format);

        return (new Response())
            ->setCode(200)
            ->setOutputFormat(null)
            ->setBody(function() use($url) {
                $stream = fopen($url, 'r');
                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }

                fclose($steam);
            }
        );
    }
}
