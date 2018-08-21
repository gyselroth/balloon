<?php
use Balloon\App\Burl\Converter\Adapter as BurlConverterAdapter;
use Balloon\Converter;


return [
    Converter::class => [
        'calls' => [
            BurlConverterAdapter::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.BurlConverterAdapter::class.'}']
            ]
        ],
    ],
];
