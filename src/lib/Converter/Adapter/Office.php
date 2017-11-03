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

class Office extends Imagick
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

        parent::setOptions($config);
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
            }
        }

        return $this;
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
        if (0 === $file->getSize()) {
            return false;
        }

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
                $values = array_keys($formats);

                return array_merge($values, parent::getSupportedFormats($file));
            }
        }
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

        if (in_array($format, parent::getSupportedFormats($file), true)) {
            $convert = 'pdf';
        } else {
            $convert = $format;
        }

        $command = 'HOME='.escapeshellarg($this->tmp).' timeout '.escapeshellarg($this->timeout).' '
            .escapeshellarg($this->soffice)
            .' --headless'
            .' --invisible'
            .' --nocrashreport'
            .' --nodefault'
            .' --nofirststartwizard'
            .' --nologo'
            .' --norestore'
            .' --convert-to '.escapeshellarg($convert)
            .' --outdir '.escapeshellarg($this->tmp)
            .' '.escapeshellarg($source);

        $this->logger->debug('convert file to ['.$convert.'] using ['.$command.']', [
            'category' => get_class($this),
        ]);

        shell_exec($command);
        $temp = $this->tmp.DIRECTORY_SEPARATOR.basename($source).'.'.$convert;

        if (!file_exists($temp)) {
            throw new Exception('failed convert document into '.$convert);
        }
        $this->logger->info('converted document into ['.$convert.']', [
                'category' => get_class($this),
            ]);

        if ('pdf' === $convert && 'pdf' !== $format) {
            return $this->createFromFile($temp, $format);
        }

        return new Result($temp);
    }
}
