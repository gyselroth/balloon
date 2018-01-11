<?php
use Balloon\Migration;
use Balloon\App\Elasticsearch\Migration\Delta\Installation;
use Balloon\App\Elasticsearch\Hook as ElasticsearchHook;
use Balloon\Hook;

return [
    'service' => [
        Migration::class => [
            'adapter' => [
                Installation::class => [],
            ]
        ],
        Hook::class => [
            'adapter' => [
                ElasticsearchHook::class => []
            ],
        ],
    ]
];
