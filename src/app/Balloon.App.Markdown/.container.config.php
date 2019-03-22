<?php
use Balloon\App\Markdown\Constructor\Http;
use Balloon\App\Markdown\Converter\Adapter\Markdown;
use Balloon\Converter;
use Balloon\Converter\Adapter\AbstractOffice;
use Balloon\Converter\Adapter\Office;
use Balloon\Bootstrap\AbstractBootstrap;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Markdown' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Converter::class => [
        'calls' => [
            Markdown::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Markdown::class.'}']
            ]
        ],
    ],
];
