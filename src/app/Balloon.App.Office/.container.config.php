<?php
use Balloon\App\Office\Constructor\Http;
use Balloon\Bootstrap\AbstractBootstrap;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Office' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Http::class => [
        'arguments' => [
            'config' => [
                'loleaflet' => "{ENV(BALLOON_OFFICE_URI,https://localhost:9980/loleaflet)}/dist/loleaflet.html",
                'wopi_url' => "{ENV(BALLOON_WOPI_URL,https://localhost)}",
                'token_ttl' => 3600
            ]
        ]
    ],
];
