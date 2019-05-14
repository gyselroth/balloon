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
];
