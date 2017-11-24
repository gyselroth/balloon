<?php
use Balloon\App;

return [
    App::class => [
       'adapter' => [
            'Balloon\App\Office\App' => [
                'enabled' => 0
            ]
        ],
    ],
];
