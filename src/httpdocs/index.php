<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

use Balloon\Bootstrap\Http;
use Micro\Config\Config;
use Micro\Config\Struct;
use Micro\Config\Xml;

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'));

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

set_include_path(implode(PATH_SEPARATOR, [
    constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
    constant('APPLICATION_PATH').DIRECTORY_SEPARATOR,
    get_include_path(),
]));

$composer = require 'vendor/autoload.php';

if (extension_loaded('apc') && apc_exists('config')) {
    $config = apc_fetch('config');
} else {
    $file = constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml';
    $default = require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.container.config.php';
    $config = new Config(new Struct($default));

    if (is_readable($file)) {
        $xml = new Xml(constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml', constant('APPLICATION_ENV'));
        $config->inject($xml);
    }

    if (extension_loaded('apc')) {
        //apc_store('config', $config);
    }
}

new Http($composer, $config);
