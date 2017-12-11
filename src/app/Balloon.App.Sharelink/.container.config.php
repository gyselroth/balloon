<?php
use Balloon\Migration;
use Balloon\App\Sharelink\Migration\Delta\Installation;
use Balloon\App\Sharelink\Migration\Delta\SharelinkIntoApp;

return [
    Migration::class => [
        'adapter' => [
            Installation::class => [],
            SharelinkIntoApp::class => [],
        ]
    ],
];
