<?php
use Balloon\Migration;
use Balloon\App\Sharelink\Migration\Delta\Installation;
use Balloon\App\Sharelink\Migration\Delta\SharelinkIntoApp;

return [
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
            SharelinkIntoApp::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.SharelinkIntoApp::class.'}']
            ]
        ],
    ]
];
