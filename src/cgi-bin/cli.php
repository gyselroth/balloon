#!/usr/bin/env php
<?php
/**
 * Balloon.
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2017 gyselroth GmbH
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
defined('BALLOON_PATH')
    || define('BALLOON_PATH', (getenv('BALLOON_PATH') ? getenv('BALLOON_PATH') : realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..')));

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
new Balloon\Bootstrap\Cli($composer);
