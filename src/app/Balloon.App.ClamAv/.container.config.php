<?php
use Balloon\Hook;
use Balloon\App\ClamAv\Hook as ClamAvHook;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\ClamAv\Constructor\Http;
use Balloon\App\ClamAv\Scanner;

return [
    Scanner::class => [
        'arguments' => [
            'config' => [
                'socket' => "{ENV(BALLOON_CLAMAV_URI,unix:///var/run/clamav/clamd.ctl)}"
            ]
        ]
    ],
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
