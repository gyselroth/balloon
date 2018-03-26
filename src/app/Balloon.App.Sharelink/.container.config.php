<?php
use Balloon\Migration;
use Balloon\App\Sharelink\Migration\Delta\Installation;
use Balloon\App\Sharelink\Migration\Delta\SharelinkIntoApp;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Sharelink\Constructor\Http;

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
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Sharelink' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
