<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

use Balloon\Testsuite\Test;

require_once './vendor/autoload.php';
require_once './tests/Test.php';

$test = new Test();
$test->request('DELETE', '/collection?p=/&force=1');
