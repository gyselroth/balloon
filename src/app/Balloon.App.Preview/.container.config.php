<?php
use Balloon\Hook;
use Balloon\App\Preview\Hook as PreviewHook;
use Balloon\Migration;
use Balloon\App\Preview\Migration\Delta\Installation;
use Balloon\App\Preview\Migration\Delta\PreviewIntoApp;

return [
    Hook::class => [
        'adapter' => [
            PreviewHook::class => []
        ],
    ],
    Migration::class => [
        'adapter' => [
            Installation::class => [],
            PreviewIntoApp::class => [],
        ]
    ],
];
