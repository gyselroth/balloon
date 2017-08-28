<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use \Balloon\Filesystem\Node\File;
use \Micro\Config;
use \Balloon\App\Preview\Exception;

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
     * Set options
     *
     * @param  Config $config
     * @return Office
     */
    public function setOptions(Config $config): PreviewInterface
    {
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

        //TODO: match office document
        return true;
    }


    /**
     * Get thumbnail
     *
     * @param  File $file
     * @return string
     */
    public function create(File $file): string
    {
        $sourceh = tmpfile();
        $source = stream_get_meta_data($sourceh)['uri'];
        stream_copy_to_stream($file->get(), $sourceh);

        $command = "HOME=".escapeshellarg($this->tmp)." timeout ".escapeshellarg($this->timeout)." "
            .escapeshellarg($this->soffice)
            ." --headless"
            ." --invisible"
            ." --nocrashreport"
            ." --nodefault"
            ." --nofirststartwizard"
            ." --nologo"
            ." --norestore"
            ." --convert-to pdf"
            ." --outdir ".escapeshellarg($this->tmp)
            ." ".escapeshellarg($source);

        $this->logger->debug('convert file to pdf using ['.$command.']', [
            'category' => get_class($this)
        ]);

        $o = shell_exec($command);
        $pdf = $this->tmp.DIRECTORY_SEPARATOR.basename($source).'.pdf';

        if (!file_exists($pdf)) {
            throw new Exception('failed convert office file to pdf');
        } else {
            $this->logger->info('pdf file ['.$pdf.'] created', [
                'category' => get_class($this),
            ]);
                        
            $return = $this->createFromFile($pdf);
            unlink($pdf);
            return $return;
        }
    }
}
