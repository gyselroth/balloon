#!/usr/bin/env php
<?php
/**
 * Balloon.
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2017 gyselroth GmbH
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
define('BALLOON_PATH', (getenv('BALLOON_PATH') ? getenv('BALLOON_PATH') : realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..')));

define('BALLOON_CONFIG_DIR', (getenv('BALLOON_CONFIG_DIR') ? getenv('BALLOON_CONFIG_DIR') : constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'config'));
!getenv('BALLOON_CONFIG_DIR') ? putenv('BALLOON_CONFIG_DIR='.constant('BALLOON_CONFIG_DIR')) : null;

define('BALLOON_LOG_DIR', (getenv('BALLOON_LOG_DIR') ? getenv('BALLOON_LOG_DIR') : constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'log'));
!getenv('BALLOON_LOG_DIR') ? putenv('BALLOON_LOG_DIR='.constant('BALLOON_LOG_DIR')) : null;

set_include_path(implode(PATH_SEPARATOR, [
    constant('BALLOON_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
    constant('BALLOON_PATH').DIRECTORY_SEPARATOR,
    get_include_path(),
]));

$composer = require 'vendor/autoload.php';
// @codeCoverageIgnoreStart
$dic = Balloon\Bootstrap\ContainerBuilder::get($composer);
$dic->get(Balloon\Bootstrap\Cli::class)->process();
// @codeCoverageIgnoreEnd

