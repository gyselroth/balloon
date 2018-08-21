<?php
use Balloon\App\Burl\Converter\Adapter as BurlConverterAdapter;
use Balloon\Converter\Adapter\AdapterInterface;


return [
    AdapterInterface::class => [
        'calls' => [
            BurlConverterAdapter::class => [
                'method' => 'inject',
                'arguments' => ['hook' => '{'.BurlConverterAdapter::class.'}']
            ]
        ],
    ],
];
