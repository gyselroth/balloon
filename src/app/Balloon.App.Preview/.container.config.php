<?php
use Balloon\Hook;
use Balloon\App\Preview\Hook as PreviewHook;

return [
    Hook::class => [
        'adapter' => [
            PreviewHook::class => []
        ],
    ],
];
