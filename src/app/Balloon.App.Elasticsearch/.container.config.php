<?php
use Balloon\Migration;
use Balloon\App\Elasticsearch\Migration\Delta\Installation;
use Balloon\App\Elasticsearch\Hook as ElasticsearchHook;
use Balloon\Hook;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Elasticsearch\Constructor\Http;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Balloon\Bootstrap\Cli as CliBootstrap;
use Balloon\App\Elasticsearch\Constructor\Cli;

return [
    Client::class => [
        'use' => ClientBuilder::class,
        'factory' => 'create',
        'calls' => [
            [
                'method' => 'setHosts',
                'arguments' => ['hosts' => ["{ENV(BALLOON_ELASTICSEARCH_URI,http://localhost:9200)}"]]
            ],
            [
                'method' => 'build',
                'select' => true,
            ]
        ],
    ],
    CliBootstrap::class => [
        'calls' => [
            'Balloon.App.Elasticsearch' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Cli::class.'}']
            ],
        ]
    ],
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
        ]
    ],
    Hook::class => [
        'calls' => [
            ElasticsearchHook::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.ElasticsearchHook::class.'}']
            ],
        ]
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Elasticsearch' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
