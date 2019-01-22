<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

use Balloon\Bootstrap\ContainerBuilder;
use Balloon\Bootstrap\Http;
use Micro\Http\Response;

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

try {
    $dic = ContainerBuilder::get($composer);
    $http = $dic->get(Http::class);
} catch (\Exception $e) {
    error_log((string) $e);

    (new Response())
        ->setCode(500)
        ->setBody([
            'error' => get_class($e),
            'message' => $e->getMessage(),
        ])->send();

    exit();
}

$http->process();
