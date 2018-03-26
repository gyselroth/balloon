<?php
use Balloon\Hook;
use Balloon\App\Notification\Hook\NewShareAdded;
use Balloon\App\Notification\Hook\Subscription;
use Balloon\App\Notification\Adapter\Db;
use Balloon\App\Notification\Adapter\Mail;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Notification\Constructor\Http;

return [
    Notifier::class => [
        'calls' => [
            Db::class => [
                'method' => 'injectAdapter',
                'arguments' => '{'.Db::class.'}'
            ],
            Mail::class => [
                'method' => 'injectAdapter',
                'arguments' => '{'.Mail::class.'}'
            ]
        ]
    ],
    Hook::class => [
        'calls' => [
            NewShareAdded::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.NewShareAdded::class.'}']
            ],
            Subscripiton::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Subscription::class.'}']
            ]
        ],
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Notification' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
];
