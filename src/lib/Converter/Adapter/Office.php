<?php

declare(strict_types=1);

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

class Office implements AdapterInterface
{
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
            'odp'  => 'application/vnd.oasis.opendocument.presentation',
            'fodp' => 'application/vnd.oasis.opendocument.presentation-flat-xml',
            'otp'  => 'application/vnd.oasis.opendocument.presentation-template',
        ]
    ];

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
                    unset($config[$option]);
                    break;
                case 'tmp':
                    if (!is_writeable($value)) {
                        throw new Exception('tmp option must be a writable directory');
                    }

                    $this->tmp = (string) $value;
                    unset($config[$option]);

                    break;
                case 'timeout':
                    if (!is_numeric($value)) {
                        throw new Exception('timeout option must be a number');
                    }

                    $this->timeout = (string) $value;
                    unset($config[$option]);

                    break;
            }
        }

        return parent::setOptions($config);
    }

    /**
     * Return match.
     *
     * @param File $file
     *
     * @return bool
     */
    public function match(File $file): bool
    {
        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getMime(), $formats, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get supported formats.
     *
     * @param File $file
     *
     * @return array
     */
    public function getSupportedFormats(File $file): array
    {
        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getMime(), $formats, true)) {
                return array_keys($formats);
            }
        }

        return [];
    }


    /**
     * Convert.
     *
     * @param File   $file
     *
     * @return Result
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

        if ($height <= $width && $width > $this->max_size) {
            $image->scaleImage($this->max_size, 0);
        } elseif ($height > $this->max_size) {
            $image->scaleImage(0, $this->max_size);
        }

        $image->setImageCompression(SystemImagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(100);
        $image->stripImage();
        $image->setColorSpace(SystemImagick::COLORSPACE_SRGB);
        $image->setImageFormat($format);
        $image->writeImage($dest);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed create prevew');
        }

        return new Result($dest, $desth);
    }


    /**
     * Convert.
     *
     * @param File   $file
     * @param string $format
     *
     * @return Result
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

        $this->logger->debug('convert file to ['.$convert.'] using ['.$command.']', [
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
