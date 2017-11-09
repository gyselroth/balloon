<?php
use Balloon\Database;
use Balloon\App\Sharelink\Database\Delta\Installation;
use Balloon\App\Sharelink\Database\Delta\SharelinkIntoApp;

return [
    Database::class => [
        'adapter' => [
            Installation::class => [],
            SharelinkIntoApp::class => [],
        ]
    ],
];
