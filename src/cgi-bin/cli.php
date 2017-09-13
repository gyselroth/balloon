#!/usr/bin/env php
<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2017 gyselroth GmbH
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'));

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

set_include_path(implode(PATH_SEPARATOR, [
    APPLICATION_PATH.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
    APPLICATION_PATH,
    get_include_path(),
]));

$composer = require 'vendor/autoload.php';

$file = APPLICATION_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml';
if(is_readable($file)) {
    $xml = new \Micro\Config\Xml(APPLICATION_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml', APPLICATION_ENV);
    $config = new \Micro\Config($xml);
} else {
    $config = null;
}

new \Balloon\Bootstrap\Cli($composer, $config);
