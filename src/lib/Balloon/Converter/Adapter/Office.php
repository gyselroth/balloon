<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use \Balloon\Filesystem\Node\File;
use \Balloon\Converter\Exception;
use \Balloon\Converter\Result;

class Office extends Imagick
{
    /**
     * Soffice executable
     *
     * @var string
     */
    protected $soffice = '/usr/bin/soffice';


    /**
     * Timeout
     *
     * @var string
     */
    protected $timeout = '10';


    /**
     * Tmp
     *
     * @var string
     */
    protected $tmp = '/tmp';


    /**
     * Formats
     *
     * @var array
     */
    protected $formats = [
        'spreadsheet' => [
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
            'fods' => 'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
            'xls'  => 'application/vnd.ms-excel',
            'xlb'  => 'application/vnd.ms-excel',
            'xlt'  => 'application/vnd.ms-excel',
            'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'  => 'text/csv'
        ]
    ];


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function setOptions(Iterable $config=null): AdapterInterface
    {
        if ($config === null) {
            return $this;
        }

        parent::setOptions($config);
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'soffice':
                    if (!is_file($value)) {
                        throw new Exception('soffice option must be a path to an executable office suite');
                    }

                    $this->soffice = (string)$value;
                    break;
                case 'tmp':
                    if (!is_writeable($value)) {
                        throw new Exception('tmp option must be a writable directory');
                    }
            
                    $this->tmp = (string)$value;
                    break;
                case 'timeout':
                    if (!is_numeric($value)) {
                        throw new Exception('timeout option must be a number');
                    }
            
                    $this->timeout = (string)$value;
                    break;
            }
        }

        return $this;
    }


    /**
     * Return match
     *
     * @param   File $file
     * @return  bool
     */
    public function match(File $file): bool
    {
        if ($file->getSize() === 0) {
            return false;
        }
        
        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getMime(), $formats)) {
                return true;
            }
        }

        return false;
    }

    
    /**
     * Get supported formats
     *
     * @param  File $file
     * @return array
     */
    public function getSupportedFormats(File $file): array
    {
        foreach ($this->formats as $type => $formats) {
            if (in_array($file->getMime(), $formats)) {
                $values = array_keys($formats);
                return array_merge($values, parent::getSupportedFormats($file));
            }
        }
    }
    

    /**
     * Convert
     *
     * @param  File $file
     * @param  string $format
     * @return Result
     */
    public function convert(File $file, string $format): Result
    {
        $sourceh = tmpfile();
        $source = stream_get_meta_data($sourceh)['uri'];
        stream_copy_to_stream($file->get(), $sourceh);

        if (in_array($format, parent::getSupportedFormats($file))) {
            $convert = 'pdf';
        } else {
            $convert =  $format;
        }

        $command = "HOME=".escapeshellarg($this->tmp)." timeout ".escapeshellarg($this->timeout)." "
            .escapeshellarg($this->soffice)
            ." --headless"
            ." --invisible"
            ." --nocrashreport"
            ." --nodefault"
            ." --nofirststartwizard"
            ." --nologo"
            ." --norestore"
            ." --convert-to ".escapeshellarg($convert)
            ." --outdir ".escapeshellarg($this->tmp)
            ." ".escapeshellarg($source);

        $this->logger->debug('convert file to ['.$convert.'] using ['.$command.']', [
            'category' => get_class($this)
        ]);

        shell_exec($command);
        $temp = $this->tmp.DIRECTORY_SEPARATOR.basename($source).'.'.$convert;

        if (!file_exists($temp)) {
            throw new Exception('failed convert document into '.$convert);
        } else {
            $this->logger->info('converted document into ['.$convert.']', [
                'category' => get_class($this),
            ]);
                                    
            if ($convert === 'pdf' && $format !== 'pdf') {
                return $this->createFromFile($temp, $format);
            } else {
                return new Result($temp);
            }
        }
    }
}
