<?php
use Balloon\Hook;
use Balloon\App\Preview\Hook as PreviewHook;
use Balloon\Database;
use Balloon\App\Preview\Database\Delta\Installation;
use Balloon\App\Preview\Database\Delta\PreviewIntoApp;

return [
    Hook::class => [
        'adapter' => [
            PreviewHook::class => []
        ],
    ],
    Database::class => [
        'adapter' => [
            Installation::class => [],
            PreviewIntoApp::class => [],
        ]
    ],
];
