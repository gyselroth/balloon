<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Burl\Converter\Adapter;

use Balloon\Converter\Adapter\AdapterInterface;
use Balloon\Converter\Exception;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamWrapper;
use Imagick;
use Psr\Log\LoggerInterface;

class Burl implements AdapterInterface
{
    /**
     * Preview format.
     */
    const PREVIEW_FORMAT = 'png';

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
     * Browserlerss microservice url.
     *
     * @var string
     */
    protected $browserlessUrl = 'https://chrome.browserless.io';

    /**
     * Timeout.
     *
     * @var string
     */
    protected $timeout = '10';

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
     *
     * @param iterable $config
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     */
    public function setOptions(Iterable $config = null): AdapterInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'browserlessUrl':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new Exception('browserlessUrl option must be a valid url to a browserless instance');
                    }

                    $this->browserlessUrl = (string) $value;

                    break;
                case 'timeout':
                    if (!is_numeric($value)) {
                        throw new Exception('timeout option must be a number');
                    }

                    $this->timeout = (string) $value;

                    break;
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
    public function createPreview(File $file): Result
    {
        try {
            $imageFile = $this->getImage(\stream_get_contents($file->get()), self::PREVIEW_FORMAT);
        } catch (Exception $e) {
            throw new Exception('failed create preview');
        }
        $image = new Imagick($imageFile->getPath());

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        if ($height <= $width && $width > $this->preview_max_size) {
            $image->scaleImage($this->preview_max_size, 0);
        } elseif ($height > $this->preview_max_size) {
            $image->scaleImage(0, $this->preview_max_size);
        }

        $image->writeImage($imageFile->getPath());

        return $imageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function convert(File $file, string $format): Result
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
    protected function getImage(string $url, string $format): Result
    {
        $options = [
            'fullPage' => false,
            'type' => $format,
        ];
        if ('jpeg' === $format) {
            $options['quality'] = 75;
        }

        $this->logger->debug('request screenshot from ['.$this->browserlessUrl.'/screenshot'.'] using url ['.$url.']', [
            'category' => get_class($this),
        ]);

        $response = $this->client->request(
            'POST',
            $this->browserlessUrl.'/screenshot',
            [
                'connect_timeout' => $this->timeout,
                'timeout' => $this->timeout,
                'json' => [
                    'url' => $url,
                    'options' => $options,
                ],
            ]
        );

        return $this->getResponseIntoResult($response, $format);
    }

    /**
     * Get pdf of url contents.
     */
    protected function getPdf(string $url): Result
    {
        $this->logger->debug('request pdf from ['.$this->browserlessUrl.'/pdf'.'] using url ['.$url.']', [
            'category' => get_class($this),
        ]);

        $response = $this->client->request(
            'POST',
            $this->browserlessUrl.'/pdf',
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

        return $this->getResponseIntoResult($response, 'pdf');
    }

    /**
     * Turn PSR7-Response into a Result.
     */
    protected function getResponseIntoResult(Response $response, string $format): Result
    {
        $desth = tmpfile();
        $dest = stream_get_meta_data($desth)['uri'];

        stream_copy_to_stream(StreamWrapper::getResource($response->getBody()), $desth);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed get '.$format);
        }

        return new Result($dest, $desth);
    }
}
