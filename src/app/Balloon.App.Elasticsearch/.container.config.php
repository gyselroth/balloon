<?php
use Balloon\Migration;
use Balloon\App\Elasticsearch\Migration\Delta\Installation;
use Balloon\App\Elasticsearch\Migration\Delta\v6;
use Balloon\App\Elasticsearch\Hook as ElasticsearchHook;
use Balloon\Hook;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Elasticsearch\Constructor\Http;
use Balloon\App\Elasticsearch\Elasticsearch;

return [
    Elasticsearch::class => [
        'arguments' => [
            'config' => [
                'server' => "{ENV(BALLOON_ELASTICSEARCH_URI,http://localhost:9200)}"
            ]
        ]
    ],
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
            v6::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.v6::class.'}']
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
