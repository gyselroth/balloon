<?php
use Balloon\App\Office\Constructor\Http;
use Balloon\App\Office\Converter\Adapter\Office;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\Converter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;

return [
    ClientInterface::class => [
        'use' => Client::class,
    ],
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
    Converter::class => [
        'calls' => [
            Office::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Office::class.'}']
            ]
        ],
    ],
    Office::class => [
        'services' => [
            ClientInterface::class => [
                'arguments' => [
                    'config' => [
                        'debug' => true,
                        'base_uri' => 'https://libreoffice:9980',
                        'connect_timeout' => 3,
                        'timeout' => 10,
                        'verify' => false,
                    ]
                ]
            ]
        ]
    ]
];
