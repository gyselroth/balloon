<?php

declare(strict_types = 1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use Balloon\Converter\Exception;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use Imagick;
use Psr\Log\LoggerInterface;

class ImagickImage implements AdapterInterface
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
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'tiff' => 'image/tiff',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];

    /**
     * Match convert filter.
     *
     * @var string
     */
    protected $match_convert = '#^image\/#';

    /**
     * Match preview filter.
     *
     * @var string
     */
    protected $match_preview = '#^image|text\/#';

    /**
     * Initialize.
     *
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return AdapterInterface
     */
    public function setOptions(?Iterable $config = null) : AdapterInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'preview_max_size':
                    $this->preview_max_size = (int)$value;

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
        return (bool)preg_match($this->match_convert, $file->getContentType());
    }

    /**
     * {@inheritdoc}
     */
    public function matchPreview(File $file): bool
    {
        return preg_match($this->match_preview, $file->getContentType()) || isset($this->formats[$file->getExtension()]);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFormats(File $file): array
    {
        return array_keys($this->formats);
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file): Result
    {
        $sourceh = tmpfile();
        $source = stream_get_meta_data($sourceh)['uri'];
        stream_copy_to_stream($file->get(), $sourceh);
        $desth = tmpfile();
        $dest = stream_get_meta_data($desth)['uri'];

        $image = new Imagick($source.'[0]');

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        if ($height <= $width && $width > $this->preview_max_size) {
            $image->scaleImage($this->preview_max_size, 0);
        } elseif ($height > $this->preview_max_size) {
            $image->scaleImage(0, $this->preview_max_size);
        }

        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(100);
        $image->stripImage();
        $image->setImageFormat(self::PREVIEW_FORMAT);
        $image->writeImage($dest);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed convert file');
        }

        return new Result($dest, $desth);
    }

    /**
     * {@inheritdoc}
     */
    public function convert(File $file, string $format): Result
    {
        $sourceh = tmpfile();
        $source = stream_get_meta_data($sourceh)['uri'];
        stream_copy_to_stream($file->get(), $sourceh);

        $desth = tmpfile();
        $dest = stream_get_meta_data($desth)['uri'];

        $image = new Imagick($source);
        //$image->setImageCompression(SystemImagick::COMPRESSION_JPEG);
        //$image->setImageCompressionQuality(100);
        //$image->stripImage();
        //$image->setColorSpace(SystemImagick::COLORSPACE_SRGB);
        $image->setImageFormat($format);
        $image->writeImage($dest);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed convert file');
        }

        return new Result($dest, $desth);
    }
}
