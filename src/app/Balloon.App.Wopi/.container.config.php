<?php
use Balloon\App\Wopi\Constructor\Http;
use Balloon\Bootstrap\AbstractBootstrap;
use Micro\Auth\Auth;
use Balloon\App\Wopi\HostManager;
use Balloon\App\Wopi\Auth\Token;
use Balloon\App\Wopi\Migration\Delta\Installation;
use Balloon\App\Wopi\Migration\Delta\RemoveOldTokenCollection;
use Balloon\Migration;

return [
    HostManager::class => [
        'arguments' => [
            'config' => [
                'hosts' => [
                    [
                        'name' => 'LibreOffice Online',
                        'url' => '{ENV(BALLOON_LIBREOFFICE_COLLAB_URL,https://libreoffice:9980)}/hosting/discovery',
                        'wopi_url' => '{ENV(BALLOON_LIBREOFFICE_COLLAB_WOPI_URL,http://localhost/wopi)}',
                    ]
                ]
            ]
        ],
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Wopi' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Auth::class => [
        'calls' => [
            Token::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Token::class.'}']
            ],
        ],
    ],
    Migration::class => [
        'calls' => [
            RemoveOldTokenCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.RemoveOldTokenCollection::class.'}']
            ],
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
        ]
    ],
];
