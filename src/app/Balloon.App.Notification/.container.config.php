<?php
use Balloon\Hook;
use Balloon\App\Notification\Hook\NewShareAdded;
use Balloon\App\Notification\Adapter\Db;
use Balloon\App\Notification\Adapter\Mail;

return [
    'service' => [
        Notifier::class => [
            'adapter' => [
                Db::class => [],
                Mail::class => [],
            ]
        ],
        Hook::class => [
            'adapter' => [
                NewShareAdded::class => []
            ],
        ],
    ]
];
