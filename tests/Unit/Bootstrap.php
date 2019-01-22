<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'));

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

set_include_path(implode(PATH_SEPARATOR, [
    constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
    constant('APPLICATION_PATH'),
    get_include_path(),
]));

$composer = require './vendor/autoload.php';

foreach (glob(constant('APPLICATION_PATH').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
    $app = basename($app);
    $ns = str_replace('.', '\\', $app).'\\';
    $composer->addPsr4($ns, constant('APPLICATION_PATH')."/src/app/$app");
}
