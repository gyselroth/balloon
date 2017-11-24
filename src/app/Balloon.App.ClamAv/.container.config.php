<?php
use Balloon\Hook;
use Balloon\App\ClamAv\Hook as ClamAvHook;

return [
    Hook::class => [
        'adapter' => [
            ClamAvHook::class => []
        ],
    ],
    App::class => [
       'adapter' => [
            'Balloon\App\ClamAv\App' => [
                'enabled' => 0
            ]
        ],
    ],
];
