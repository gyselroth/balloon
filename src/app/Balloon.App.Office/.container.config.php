<?php
use Balloon\App\Office\Converter\Adapter\Office;
use Balloon\Converter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;

return [
    ClientInterface::class => [
        'use' => Client::class,
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
                        'base_uri' => '{ENV(BALLOON_LIBREOFFICE_CONVERT_URL,https://libreoffice:9980)}',
                        'connect_timeout' => 3,
                        'timeout' => 10,
                    ]
                ]
            ]
        ]
    ],
];
