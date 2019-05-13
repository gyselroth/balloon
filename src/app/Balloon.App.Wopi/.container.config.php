<?php
use Balloon\App\Wopi\Constructor\Http;
use Balloon\Bootstrap\AbstractBootstrap;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Wopi' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
