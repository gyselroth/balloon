<?php
use Balloon\Hook;
use Balloon\App\Convert\Hook as ConvertHook;

return [
    Hook::class => [
        'calls' => [
            ConvertHook::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.ConvertHook::class.'}']
            ]
        ],
    ],
];
