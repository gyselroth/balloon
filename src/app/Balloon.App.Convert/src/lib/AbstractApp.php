<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use \Balloon\App\AbstractApp as CoreAbstractApp;
use \Balloon\App\AppInterface;
use \Balloon\Converter;

abstract class AbstractApp extends CoreAbstractApp
{
    /**
     * Converter
     *
     * @var Converter
     */
    protected $converter;

       
    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AppInterface
     */
    public function setOptions(?Iterable $config=null): AppInterface
    {
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
