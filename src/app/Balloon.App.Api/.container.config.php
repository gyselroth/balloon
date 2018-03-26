<?php
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Api\Constructor\Http;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Api' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
