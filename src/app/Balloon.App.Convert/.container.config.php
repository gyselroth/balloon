<?php
use Balloon\Hook;
use Balloon\App\Convert\Hook as ConvertHook;
use Balloon\Migration;
use Balloon\App\Convert\Migration\Delta\Installation;

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
    ]

];
