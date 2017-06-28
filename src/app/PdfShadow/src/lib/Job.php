<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\PdfShadow;

use \Psr\Log\LoggerInterface as Logger;
use \Micro\Config;
use \Balloon\Filesystem;
use \MongoDB\Database;
use \Balloon\Queue\AbstractJob;

class Job extends AbstractJob
{
    /**
     * soffice
     *
     * @var string
     */
    protected $soffice = '/usr/bin/soffice';
    
    
    /**
     * tmp
     *
     * @var string
     */
    protected $tmp = '/tmp';


    /**
     * Timeout
     *
     * @var int
     */
    protected $timeout = 10;


    /**
     * Run job
     *
     * @return bool
     */
    public function run(Filesystem $fs, Logger $logger, Config $config): bool
    {
        $file = $fs->findNodeWithId($this->data['id']);

        $logger->info("create preview for [".$this->data['id']."]", [
            'category' => get_class($this),
        ]);

        $this->create($file);

        $name   = $file->getName().'.pdf';
        $parent = $file->getParent();

        if($parent->childExists($name)) {
            $parent->getChild($name)->put($pdf);
        } else {
            $parent->createFile($name, $pdf);
        }

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
            throw new Exception('failed convert file to pdf');
        } 

        
        $this->logger->info('pdf file ['.$pdf.'] created', [
            'category' => get_class($this),
        ]);
                        
        return $pdf;
    }
}
