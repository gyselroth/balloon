<?php
use Balloon\App\Burl\Constructor\Http;
use Balloon\App\Burl\Converter\Adapter\Burl;
use Balloon\Converter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Balloon\Bootstrap\AbstractBootstrap;

return [
    ClientInterface::class => [
        'use' => Client::class,
    ],
    Burl::class => [
        'services' => [
            ClientInterface::class => [
                'arguments' => [
                    'config' => [
                        'base_uri' => '{ENV(BALLOON_BURL_BROWSERLESS_URL,https://chrome.browserless.io)}',
                        'connect_timeout' => 3,
                        'timeout' => 10,
                    ]
                ]
            ]
        ]
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Burl' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Converter::class => [
        'calls' => [
            Burl::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Burl::class.'}']
            ]
        ],
    ],
];
