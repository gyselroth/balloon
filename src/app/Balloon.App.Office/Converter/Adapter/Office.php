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
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\StreamWrapper;
use Imagick;
use Psr\Log\LoggerInterface;

class Office implements AdapterInterface
{
    /**
     * Preview format.
     */
    const PREVIEW_FORMAT = 'png';

    /**
     * Additional destination formats supported by this adapter
     */
    const DEST_FORMATS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'tiff' => 'image/tiff',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];

    /**
     * GuzzleHttpClientInterface.
     *
     * @var GuzzleHttpClientInterface
     */
    protected $client;

    /**
     * preview max size.
     *
     * @var int
     */
    protected $preview_max_size = 500;

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
        'spreadsheet' => [
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'fods' => 'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
            'xls' => 'application/vnd.ms-excel',
            'xlb' => 'application/vnd.ms-excel',
            'xlt' => 'application/vnd.ms-excel',
            'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',
            'xslb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
            'xltm' => 'application/vnd.ms-excel.template.macroEnabled.12',
        ],
        'text' => [
            'odt' => 'application/vnd.oasis.opendocument.text',
            'fodt' => 'application/vnd.oasis.opendocument.text-flat-xml',
            'ott' => 'application/vnd.oasis.opendocument.text-template',
            'oth' => 'application/vnd.oasis.opendocument.text-web',
            'odm' => 'application/vnd.oasis.opendocument.text-master',
            'otm' => 'application/vnd.oasis.opendocument.text-master-template',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'doc' => 'application/msword',
            'dot' => 'application/msword',
            'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
            'dotm' => 'application/vnd.ms-word.template.macroEnabled.12',
            'txt' => 'text/plain',
            'html' => 'text/html',
        ],
        'presentation' => [
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
            'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
            'ppam' => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
            'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
            'sldm' => 'application/vnd.ms-powerpoint.slide.macroEnabled.12',
            'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
            'potm' => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'fodp' => 'application/vnd.oasis.opendocument.presentation-flat-xml',
            'otp' => 'application/vnd.oasis.opendocument.presentation-template',
        ],
    ];

    /**
     * Destination formats
     *
     * @param array
     */
    protected $dest_formats = [];

    /**
     * Initialize.
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger, array $config = [])
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->setOptions($config);

        $this->dest_formats = $this->formats;
        $this->dest_formats['spreadsheet'] = array_merge($this->dest_formats['spreadsheet'], self::DEST_FORMATS);
        $this->dest_formats['text'] = array_merge($this->dest_formats['text'], self::DEST_FORMATS);
        $this->dest_formats['presentation'] = array_merge($this->dest_formats['presentation'], self::DEST_FORMATS);
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
        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getContentType(), $formats, true)) {
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
        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getContentType(), $formats, true)) {
                return array_keys($this->dest_formats[$type]);
            }
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file)
    {
        return $this->createPreviewFromStream($file->get());
    }

    /**
     * {@inheritdoc}
     */
    public function convert(File $file, string $format)
    {
        return $this->convertFromStream($file->get(), $format);
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
