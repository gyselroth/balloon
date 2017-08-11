<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CleanTemp;

use \Balloon\Exception;
use \Balloon\Filesystem;

class Cli extends AbstractApp
{
    /**
     * Tmp dir
     *
     * @var string
     */
    protected $dir = '/tmp/balloon';


    /**
     * max age
     *
     * @var int
     */
    protected $max_age = 3600;


    /**
     * force check owner
     *
     * @var int
     */
    protected $force_check_owner = 0;


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return PluginInterface
     */
    public function setOptions(?Iterable $config): PluginInterface
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'dir':
                    if (empty($value) || $value == '/') {
                        throw new Exception('temp folder for temp cleaner can not be empty or root');
                    }
                    $this->dir = $value;
                break;

                case 'max_age':
                case 'force_check_owner':
                    $this->{$option} = (int)$value;
                break;
            }
        }

        return $this;
    }


    /**
     * Execute plugin
     *
     * @param   Filesystem $fs
     * @return  void
     */
    public function start(Filesystem $fs): void
    {
        $this->logger->info("check for old temporary files in [".$this->dir."]", [
            'category' => get_class($this),
        ]);

        $this->_clean($this->dir);
    }

    
    /**
     * Clean temp
     *
     * @param   string $dir
     * @return  void
     */
    protected function _clean(string $dir): void
    {
        $files = glob($dir.DIRECTORY_SEPARATOR."*");
        $time  = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($time - filemtime($file) >= $this->max_age) {
                    $owner = $this->force_check_owner;
                    if (!empty($owner) && fileowner($file) != $owner) {
                        $this->logger->debug("skip non owned file [".$file."]", [
                            'category' => get_class($this),
                        ]);

                        continue;
                    }

                    $result = unlink($file);
                    $this->logger->debug("clean temp file [".$file."]", [
                        'category' => get_class($this),
                    ]);
                }
            } elseif (is_dir($file)) {
                $basename = basename($file);
                if ($basename != '.' && $basename != '..') {
                    $this->_clean($file);
                }
            }
        }
    }
}
