<?php
use Balloon\Migration;
use Balloon\App\Elasticsearch\Migration\Delta\Installation;
use Balloon\App\Elasticsearch\Hook as ElasticsearchHook;
use Balloon\Hook;

return [
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
];
