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

defined('BALLOON_PATH')
    || define('BALLOON_PATH', (getenv('BALLOON_PATH') ? getenv('BALLOON_PATH') : realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..')));

defined('BALLOON_ENV')
    || define('BALLOON_ENV', (getenv('BALLOON_ENV') ? getenv('BALLOON_ENV') : 'production'));

defined('BALLOON_CONFIG_DIR')
    || define('BALLOON_CONFIG_DIR', (getenv('BALLOON_CONFIG_DIR') ? getenv('BALLOON_CONFIG_DIR') : constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'config'));

defined('BALLOON_LOG_DIR')
    || define('BALLOON_LOG_DIR', (getenv('BALLOON_LOG_DIR') ? getenv('BALLOON_LOG_DIR') : constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'log'));

set_include_path(implode(PATH_SEPARATOR, [
    constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
    constant('BALLOON_PATH').DIRECTORY_SEPARATOR,
    get_include_path(),
]));

$composer = require 'vendor/autoload.php';

if (extension_loaded('apc') && apc_exists('config')) {
    $config = apc_fetch('config');
} else {
    $file = constant('BALLOON_CONFIG_DIR').DIRECTORY_SEPARATOR.'config.xml';
    $default = require constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'.container.config.php';
    $config = new Config(new Struct($default));

    if (is_readable($file)) {
        $xml = new Xml($file, constant('BALLOON_ENV'));
        $config->inject($xml);
    }

    if (extension_loaded('apc')) {
        apc_store('config', $config);
    }
}

new Http($composer, $config);
