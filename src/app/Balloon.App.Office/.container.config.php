<?php
use Balloon\App;

return [
    'service' => [
        App::class => [
           'adapter' => [
                'Balloon\App\Office\App' => [
                    'enabled' => 0
                ]
            ],
        ],
    ]
];
