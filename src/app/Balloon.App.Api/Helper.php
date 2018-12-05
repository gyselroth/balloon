<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api;

use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\File;
use Micro\Http\Response;

class Helper
{
    /**
     * Stream content.
     */
    public static function streamContent(Response $response, File $file, bool $download = false): ?Response
    {
        if (true === $download) {
            $response->setHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\''.rawurlencode($file->getName()));
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Length', (string) $file->getSize());
            $response->setHeader('Content-Transfer-Encoding', 'binary');
        } else {
            $response->setHeader('Content-Disposition', 'inline; filename*=UTF-8\'\''.rawurlencode($file->getName()));
            $response->setHeader('Content-Type', $file->getContentType());
        }

        return $response
          ->setOutputFormat(null)
          ->setBody(function () use ($file) {
              $stream = $file->get();
              $name = $file->getName();

              if (null === $stream) {
                  return;
              }

              $size = $file->getSize();
              $length = $size;
              $start = 0;
              $end = $size - 1;

              if (isset($_SERVER['HTTP_RANGE'])) {
                  header('Accept-Ranges: bytes');
                  $c_start = $start;
                  $c_end = $end;
                  list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

                  if (strpos($range, ',') !== false) {
                      header("Content-Range: bytes $start-$end/$size");

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
                      header("Content-Range: bytes $start-$end/$size");

                      throw new Exception\InvalidRange('invalid offset/limit requested');
                  }

                  $start = (int) $c_start;
                  $end = (int) $c_end;
                  $length = (int) $end - $start + 1;
                  fseek($stream, $start);
                  header('HTTP/1.1 206 Partial Content');
                  header("Content-Range: bytes $start-$end/$size");
              }

              header('Content-Length: '.$length);
              $buffer = 1024 * 8;

              while (!feof($stream) && ($p = ftell($stream)) <= $end) {
                  if ($p + $buffer > $end) {
                      $buffer = $end - $p + 1;
                  }

                  echo fread($stream, $buffer);
                  flush();
              }

              fclose($stream);
          });
    }
}
