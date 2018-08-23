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
