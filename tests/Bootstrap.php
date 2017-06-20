<?php
use Balloon\Testsuite\Test;

require_once './vendor/autoload.php';
require_once './tests/Test.php';

$test = new Test();
$test->request('DELETE', '/collection?p=/&force=1');
