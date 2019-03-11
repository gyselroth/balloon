<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Converter\Adapter;

use Balloon\Converter\Adapter\AdapterInterface;
use Balloon\Converter\Adapter\Office as Soffice;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\StreamWrapper;
use Imagick;
use Psr\Log\LoggerInterface;

class Office extends Soffice
{
    /**
     * GuzzleHttpClientInterface.
     *
     * @var GuzzleHttpClientInterface
     */
    protected $client;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Initialize.
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger, array $config = [])
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = []): AdapterInterface
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'preview_max_size':
                    $this->preview_max_size = (int) $value;

                    break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file)
    {
        try {
            $result = $this->convert($file, self::PREVIEW_FORMAT);
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
    public function convert(File $file, string $format)
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
                        'contents' => $file->get(),
                        'filename' => 'data',
                    ],
                ],
            ]
        );

        return StreamWrapper::getResource($response->getBody());
    }
}
