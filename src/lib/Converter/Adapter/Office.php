<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use Balloon\Converter\Exception;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use Imagick;
use Psr\Log\LoggerInterface;

class Office implements AdapterInterface
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
     * Soffice executable.
     *
     * @var string
     */
    protected $soffice = '/usr/bin/soffice';

    /**
     * Timeout.
     *
     * @var string
     */
    protected $timeout = '10';

    /**
     * Tmp.
     *
     * @var string
     */
    protected $tmp = '/tmp';

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
            'pdf' => 'application/pdf',
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
            'pdf' => 'application/pdf',
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
            'pdf' => 'application/pdf',
        ],
    ];

    /**
     * One way formats.
     *
     * @param array
     */
    protected $locked_formats = [
        'pdf' => 'application/pdf',
    ];

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
    public function setOptions(Iterable $config = null): AdapterInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'soffice':
                    if (!is_file($value)) {
                        throw new Exception('soffice option must be a path to an executable office suite');
                    }

                    $this->soffice = (string) $value;

                    break;
                case 'tmp':
                    if (!is_writeable($value)) {
                        throw new Exception('tmp option must be a writable directory');
                    }

                    $this->tmp = (string) $value;

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
        if (in_array($file->getContentType(), $this->locked_formats, true)) {
            return [
                array_search($file->getContentType(), $this->locked_formats, true),
            ];
        }

        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getContentType(), $formats, true)) {
                return array_keys($formats);
            }
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file): Result
    {
        //we need a pdf to create an image from the first page
        $pdf = $this->convert($file, 'pdf');

        $desth = tmpfile();
        $dest = stream_get_meta_data($desth)['uri'];

        $image = new Imagick($pdf->getPath().'[0]');

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
        $image->setColorSpace(Imagick::COLORSPACE_SRGB);
        $image->setImageFormat(self::PREVIEW_FORMAT);
        $image->writeImage($dest);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed create prevew');
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

        $command = 'HOME='.escapeshellarg($this->tmp).' timeout '.escapeshellarg($this->timeout).' '
            .escapeshellarg($this->soffice)
            .' --headless'
            .' --invisible'
            .' --nocrashreport'
            .' --nodefault'
            .' --nofirststartwizard'
            .' --nologo'
            .' --norestore'
            .' --convert-to '.escapeshellarg($format)
            .' --outdir '.escapeshellarg($this->tmp)
            .' '.escapeshellarg($source);

        $this->logger->debug('convert file to ['.$format.'] using ['.$command.']', [
            'category' => get_class($this),
        ]);

        shell_exec($command);
        $temp = $this->tmp.DIRECTORY_SEPARATOR.basename($source).'.'.$format;

        if (!file_exists($temp)) {
            throw new Exception('failed convert document into '.$format);
        }

        $this->logger->info('converted document into ['.$format.']', [
            'category' => get_class($this),
        ]);

        return new Result($temp);
    }
}
