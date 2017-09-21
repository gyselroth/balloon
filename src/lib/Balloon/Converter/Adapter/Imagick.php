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
use \Imagick as SystemImagick;
use \Balloon\Converter\Exception;
use \Balloon\Converter\Result;

class Imagick extends AbstractAdapter
{
    /**
     * Max size
     *
     * @var int
     */
    protected $max_size = 300;


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AdapterInterface
     */
    public function setOptions(?Iterable $config=null): AdapterInterface
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'max_size':
                    $this->max_size = (int)$value;
                    break;
            }
        }

        return $this;
    }


    /**
     * Check extension match
     *
     * @param   File $file
     * @return  bool
     */
    public function match(File $file): bool
    {
        if ($file->getSize() === 0) {
            return false;
        }

        $extension  = $file->getExtension();
        return in_array($extension, $this->getSupportedFormats($file));
    }

    
    /**
     * Get supported formats
     *
     * @param  File $file
     * @return array
     */
    public function getSupportedFormats(File $file): array
    {
        return array_map('strtolower', (new SystemImagick())->queryFormats());
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
        return $this->createFromFile($source, $format);
    }


    /**
     * Create from file
     *
     * @param   string $source
     * @param   string $format
     * @return  Result
     */
    public function createFromFile(string $source, string $format): Result
    {
        $desth = tmpfile();
        $dest  = stream_get_meta_data($desth)['uri'];
        
        $image = new SystemImagick($source."[0]");

        $width  = $image->getImageWidth();
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
            throw new Exception('failed convert file');
        }

        return new Result($dest, $desth);
    }
}
