<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\DesktopClient\Api\v2;

use Balloon\App\DesktopClient\DesktopClient;
use Micro\Http\Response;

class Download
{
    /**
     * Desktop client.
     *
     * @var DesktopClient
     */
    protected $client;

    public function __construct(DesktopClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get client as downlaod stream.
     */
    public function getContent(string $format): Response
    {
        $url = $this->client->getUrl($format);

        return (new Response())
            ->setCode(200)
            ->setHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\''.rawurlencode('balloon-desktop.'.$format))
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
            ->setHeader('Content-Type', 'application/octet-stream')
            ->setHeader('Content-Transfer-Encoding', 'binary')
            ->setOutputFormat(null)
            ->setBody(function () use ($url) {
                $stream = fopen($url, 'r');
                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }
                fclose($stream);
            });
    }
}
