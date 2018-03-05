<?php
use Balloon\Hook;
use Balloon\App\Preview\Hook as PreviewHook;
use Balloon\Migration;
use Balloon\App\Preview\Migration\Delta\Installation;
use Balloon\App\Preview\Migration\Delta\PreviewIntoApp;

return [
    Hook::class => [
        'calls' => [
            PreviewHook::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.PreviewHook::class.'}']
            ]
        ],
    ],
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
            PreviewIntoApp::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.PreviewIntoApp::class.'}']
            ]
        ],
    ]
];
