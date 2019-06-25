<?php
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Api\Constructor\Http;
use Balloon\Hook;
use Balloon\App\Api\Hook\Lock;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Api' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Hook::class => [
        'calls' => [
            Lock::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Lock::class.'}']
            ],
        ],
    ],
];
