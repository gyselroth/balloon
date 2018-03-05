<?php
use Balloon\App\Office\Constructor\Http;
use Balloon\App\Notification\Hook\NewShareAdded;
use Balloon\App\Notification\Hook\Subscription;
use Balloon\App\Notification\Adapter\Db;
use Balloon\App\Notification\Adapter\Mail;

return [
    Http::class => [
        'arguments' => [
            'config' => [
                'loleaflet' => "{ENV(BALLOON_OFFICE_URI,https://localhost:9980/loleaflet)}/dist/loleaflet.html",
                'wopi_url' => "{ENV(BALLOON_WOPI_URL,https://localhost)}",
                'token_ttl' => 3600
            ]
        ]
    ]
];
