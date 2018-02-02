<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\DesktopClient\Api\Latest;

use Balloon\App\DesktopClient\App\Http as App;
use Balloon\App\DesktopClient\DesktopClient;
use Balloon\Server;
use Micro\Http\Response;

class Download
{
    /**
     * Desktop client.
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
     * @api {get} /api/v2/desktop-client/:format/stream
     * @apiVersion 2.0.0
     * @apiName getStream
     * @apiGroup App\DesktopClient
     * @apiPermission none
     * @apiDescription Download balloon desktop client
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/desktop-client/exe/stream > balloon-desktop.exe"
     *
     * @apiParam (GET Parameter) {string} [format] Request client foramt (deb, rpm, exe, pkg, zip or a custom format)
     *
     * @apiSuccessExample {binary} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param string $format
     *
     * @return Response
     */
    public function getStream(string $format): Response
    {
        $url = $this->client->getUrl($format);

        return (new Response())
            ->setCode(200)
            ->setHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\''.rawurlencode('balloon-desktop.'.$format))
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
            ->setHeader('Content-Type', 'application/octet-stream')
            ->setHeader('Content-Transfer-Encoding', 'binary')
            ->setBody(
                function () use ($url) {
                    $stream = fopen($url, 'r');
                    while (!feof($stream)) {
                        echo fread($stream, 8192);
                    }

                    fclose($stream);
                }
        );
    }
}
