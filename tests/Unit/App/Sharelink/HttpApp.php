<?php
namespace Balloon\Testsuite\Unit\App\Sharelink;

use \Balloon\App\Sharelink\Http;

class HttpApp extends Http
{
    public function init(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Balloon_App_Sharelink';
    }
}
