<?php
use Balloon\Hook;
use Balloon\App\Convert\Hook as ConvertHook;

return [
    Hook::class => [
        'adapter' => [
            ConvertHook::class => []
        ],
    ],
];
