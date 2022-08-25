<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Burl\Converter\Adapter;

use Balloon\Converter\Adapter\AdapterInterface;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\StreamWrapper;
use Imagick;
use Psr\Log\LoggerInterface;

class Burl implements AdapterInterface
{
    /**
     * Preview format.
     */
    public const PREVIEW_FORMAT = 'png';

    /**
     * preview max size.
     *
     * @var int
     */
    protected $preview_max_size = 500;

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
     * Formats.
     *
     * @var array
     */
    protected $formats = [
        'burl' => 'application/vnd.balloon.burl',
    ];

    /**
     * One way formats.
     *
     * @param array
     */
    protected $target_formats = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

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
    public function match(File $file): bool
    {
        foreach ($this->formats as $format => $mimetype) {
            if ($file->getContentType() === $mimetype) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function matchPreview(File $file): bool
    {
        return $this->match($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFormats(File $file): array
    {
        return array_keys($this->target_formats);
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file)
    {
        try {
            $result = $this->getImage(\stream_get_contents($file->get()), self::PREVIEW_FORMAT);
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
        switch ($format) {
            case 'pdf':
                return $this->getPdf(\stream_get_contents($file->get()));
            case 'png':
                return $this->getImage(\stream_get_contents($file->get()), 'png');
            case 'jpg':
            case 'jpeg':
                return $this->getImage(\stream_get_contents($file->get()), 'jpeg');
            default:
                throw new Exception('target format ['.$format.'] not supported');
        }
    }

    /**
     * Get screenshot of url.
     */
    protected function getImage(string $url, string $format)
    {
        $options = [
            'fullPage' => false,
            'type' => $format,
        ];
        if ('jpeg' === $format) {
            $options['quality'] = 75;
        }

        $this->logger->debug('request screenshot from [/screenshot] using url ['.$url.']', [
            'category' => static::class,
        ]);

        $response = $this->client->request(
            'POST',
            'screenshot',
            [
                'json' => [
                    'url' => $url,
                    'options' => $options,
                ],
            ]
        );

        $this->logger->debug('screenshot create request ended with status code ['.$response->getStatusCode().']', [
            'category' => static::class,
        ]);

        return StreamWrapper::getResource($response->getBody());
    }

    /**
     * Get pdf of url contents.
     */
    protected function getPdf(string $url)
    {
        $this->logger->debug('request pdf from [/pdf] using url ['.$url.']', [
            'category' => static::class,
        ]);

        $response = $this->client->request(
            'POST',
            'pdf',
            [
                'json' => [
                    'url' => $url,
                    'options' => [
                        'printBackground' => false,
                        'format' => 'A4',
                    ],
                ],
            ]
        );

        $this->logger->debug('pdf create request ended with status code ['.$response->getStatusCode().']', [
            'category' => static::class,
        ]);

        return StreamWrapper::getResource($response->getBody());
    }
}
