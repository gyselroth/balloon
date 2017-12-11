#!/usr/bin/env php
<?php
/**
 * Balloon.
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2017 gyselroth GmbH
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
use Balloon\Bootstrap\Cli;
use Micro\Config\Config;
use Micro\Config\Struct;
use Micro\Config\Xml;

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'));

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

set_include_path(implode(PATH_SEPARATOR, [
     constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
     constant('APPLICATION_PATH'),
    get_include_path(),
]));

$composer = require 'vendor/autoload.php';

$file = constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml';
$default = require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php';
$config = new Config(new Struct($default));

if (is_readable($file)) {
    $xml = new Xml(constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml', constant('APPLICATION_ENV'));
    $config->inject($xml);
}

new Cli($composer, $config);
