<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Converter\Adapter;

use Balloon\Converter\Adapter\AbstractOffice;
use Balloon\Converter\Exception;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\StreamWrapper;
use Imagick;
use Psr\Log\LoggerInterface;

class Office extends AbstractOffice
{
    /**
     * GuzzleHttpClientInterface.
     *
     * @var GuzzleHttpClientInterface
     */
    protected $client;

    /**
     * Initialize.
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger, array $config = [])
    {
        parent::__construct($logger, $config);
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    protected function createPreviewFromStream($stream)
    {
        try {
            $result = $this->convertFromStream($stream, self::PREVIEW_FORMAT);
        } catch (Exception $e) {
            throw new Exception('failed create preview');
        }

        $desth = tmpfile();
        stream_copy_to_stream($result, $desth);
        $dest = stream_get_meta_data($desth)['uri'];
        $image = new Imagick($dest);

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        if ($height <= $width && $width > $this->preview_max_size) {
            $image->scaleImage($this->preview_max_size, 0);
        } elseif ($height > $this->preview_max_size) {
            $image->scaleImage(0, $this->preview_max_size);
        }

        $image->writeImage($dest);

        rewind($desth);

        return $desth;
    }

    /**
     * {@inheritdoc}
     */
    protected function convertFromStream($stream, string $format)
    {
        $this->logger->debug('execute convert-to from [/lool/convert-to/'.$format.']', [
            'category' => get_class($this),
        ]);

        $response = $this->client->request(
            'POST',
            'lool/convert-to/'.$format,
            [
                'multipart' => [
                    [
                        'name' => 'data',
                        'contents' => $stream,
                        'filename' => 'data',
                    ],
                ],
            ]
        );

        $this->logger->debug('convert-to request ended with status code ['.$response->getStatusCode().']', [
            'category' => get_class($this),
        ]);

        return StreamWrapper::getResource($response->getBody());
    }
}
