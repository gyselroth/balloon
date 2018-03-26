<?php
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\DesktopClient\Constructor\Http;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.DesktopClient' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
