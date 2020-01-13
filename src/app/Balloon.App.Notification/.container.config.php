<?php
use Balloon\Hook;
use Balloon\Migration;
use Balloon\App\Notification\Migration\Delta\Installation;
use Balloon\App\Notification\Migration\Delta\AddLocale;
use Balloon\App\Notification\Hook\NewShareAdded;
use Balloon\App\Notification\Hook\Subscription;
use Balloon\App\Notification\Adapter\Db;
use Balloon\App\Notification\Adapter\Mail;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Notification\Constructor\Http;
use Balloon\App\Notification\Notifier;

return [
    Notifier::class => [
        'calls' => [
            Db::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Db::class.'}']
            ],
            Mail::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Mail::class.'}']
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
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
            AddLocale::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.AddLocale::class.'}']
            ],
        ],
    ],
];
