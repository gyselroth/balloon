<?php
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Webdav\Constructor\Http;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Webdav' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
