<?php
use Balloon\Migration;
use Balloon\App\Idp\Migration\Delta\Installation;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Idp\Constructor\Http;
use Balloon\App\Idp\Storage\MongoDB;
use OAuth2\Server as OAuth2Server;
use OAuth2\OpenID\GrantType\AuthorizationCode;
use OAuth2\GrantType\UserCredentials;
use OAuth2\GrantType\RefreshToken;
use Micro\Auth\Auth;
use Balloon\App\Idp\Auth\Token;

return [
    OAuth2Server::class => [
        'arguments' => [
            'storage' => '{'.MongoDB::class.'}',
            'config' => [
                'enforce_state' => true,
                'allow_implicit' => true,
                'use_openid_connect' => true,
                'issuer' => 'balloon'
            ],
            'grant_types' => [
                'user_credentials' => '{'.UserCredentials::class.'}',
                'refresh_token' => '{'.RefreshToken::class.'}',
            ]
        ],
        'services' => [
            UserCredentials::class => [
                'arguments' => [
                    'storage' => '{'.MongoDB::class.'}'
                ]
            ]
        ]
    ],
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Idp' => [
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
        ]
    ],
    Auth::class => [
        'calls' => [
            'token' => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Token::class.'}', 'name' => 'token']
            ],
        ],
    ],
];
