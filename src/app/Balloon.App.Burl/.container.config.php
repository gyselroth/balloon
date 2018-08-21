<?php
use Balloon\App\Burl\Constructor\Http;
use Balloon\App\Burl\Converter\Burl;
use Balloon\Converter;

return [
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
                'arguments' => ['adapter' => '{'.BurlConverterAdapter::class.'}']
            ]
        ],
    ],
];
