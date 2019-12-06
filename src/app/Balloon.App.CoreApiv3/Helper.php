<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3;

use Balloon\File\FileInterface;
use Zend\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Helper
{
    /**
     * Stream content.
     */
    public static function streamContent(ServerRequestInterface $request, FileInterface $file, bool $download = false): ResponseInterface
    {
        if (true === $download) {
            $headers = [
                'Content-Disposition' => 'attachment; filename*=UTF-8\'\''.rawurlencode($file->getName()),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string)$file->getSize(),
                'Content-Transfer-Encoding' => 'binary',
            ];
        } else {
            $headers = [
                'Content-Disposition' => 'inline; filename*=UTF-8\'\''.rawurlencode($file->getName()),
                'Content-Type' => $file->getContentType(),
            ];
        }

        $stream = $file->openReadStream();
        $response = new Response($stream, 200, $headers);
        $name = $file->getName();

        if (null === $stream) {
            return $response->withStatus(404);
        }

        $size = $file->getSize();
        $length = $size;
        $start = 0;
        $end = $size - 1;


        if ($request->hasHeader('range')) {
            $response = $response->withHeader('Accept-Ranges', 'bytes');

            $c_start = $start;
            $c_end = $end;
            list(, $range) = explode('=', $request->getHeader('range'), 2);

            if (strpos($range, ',') !== false) {
                //header("Content-Range: bytes $start-$end/$size");
                throw new Exception\InvalidRange('invalid offset/limit requested');
            }

            if ($range == '-') {
               $c_start = $size - substr($range, 1);
            } else {
               $range = explode('-', $range);
               $c_start = $range[0];
               $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
            }

            $c_end = ($c_end > $end) ? $end : $c_end;
            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
              //header("Content-Range: bytes $start-$end/$size");

                throw new Exception\InvalidRange('invalid offset/limit requested');
            }

            $start = (int) $c_start;
            $end = (int) $c_end;
            $length = (int) $end - $start + 1;
            fseek($stream, $start);

            $response = $response->withHeader('Accept-Ranges', 'bytes');
            $response = $response->withStatus(206);
            $response = $response->withHeader('Content-Range', "bytes $start-$end/$size");
        }

        $response = $response->withHeader('Content-Length', (string)$length);
        $response = $response->withBody(new Stream($stream, $end+1));
        return $response;
    }
        /*$buffer = 1024 * 8;

          while (!feof($stream) && ($p = ftell($stream)) <= $end) {
              if ($p + $buffer > $end) {
                  $buffer = $end - $p + 1;
              }

              echo fread($stream, $buffer);
              flush();
          }

          fclose($stream);
    }*/
}
