<?php
use Balloon\Hook;
use Balloon\App\ClamAv\Hook as ClamAvHook;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\ClamAv\Constructor\Http;

return [
    Hook::class => [
        'calls' => [
            ClamAvHook::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.ClamAvHook::class.'}']
            ]
        ],
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.ClamAv' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
