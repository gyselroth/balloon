<?php
use Balloon\Hook;
use Balloon\App\Convert\Hook as ConvertHook;
use Balloon\Migration;
use Balloon\App\Convert\Migration\Delta\Installation;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Convert\Constructor\Http;

return [
    Hook::class => [
        'calls' => [
            ConvertHook::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.ConvertHook::class.'}']
            ]
        ],
    ],
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
        ],
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Convert' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
