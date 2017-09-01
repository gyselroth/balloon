<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use \Balloon\App\AppInterface;
use \Balloon\App\AbstractApp;
use \Balloon\Converter;
use \Balloon\Converter\Imagick;
use \Balloon\Converter\Office;

class Cli extends AbstractApp
{
    /**
     * Converter
     *
     * @var Converter
     */
    protected $converter;

    
    /**
     * Default converter
     *
     * @return array
     */
    protected $default_converter = [
        'imagick'  => ['class' => Imagick::class],
        'office'   => ['class' => Office::class],
    ];

       
    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AppInterface
     */
    public function setOptions(?Iterable $config=null): AppInterface
    {
        if($config === null) {
            $config = $this->default_converter;
        }

        $this->converter = new Converter($this->logger, $config);
        return $this;
    }


    /**
     * Converter
     * 
     * @return Converter
     */
    public function getConverter(): Converter
    {
        return $this->converter;
    }
}
