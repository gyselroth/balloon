<?php
use Balloon\App\Burl\Constructor\Http;
use Balloon\App\Burl\Converter\Adapter\Burl;
use Balloon\Converter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;

return [
    ClientInterface::class => [
        'use' => Client::class,
    ],
    Burl::class => [
        'arguments' => [
            'config' => [
                'browserlessUrl'    => "{ENV(BALLOON_BURL_BROWSERLESS_URL,https://chrome.browserless.io)}",
                'preview_max_size'  => 500,
                'timeout'           => 10,
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
