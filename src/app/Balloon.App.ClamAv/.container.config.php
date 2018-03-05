<?php
use Balloon\Hook;
use Balloon\App\ClamAv\Hook as ClamAvHook;

return [
    Hook::class => [
        'calls' => [
            ClamAvHook::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.ClamAvHook::class.'}']
            ]
        ],
    ]
];
