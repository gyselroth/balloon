<?php
use Balloon\Migration;
use Balloon\App\Idp\Migration\Delta\Installation;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Idp\Constructor\Http;
use Balloon\App\Idp\Storage\MongoDB;
use OAuth2\Server as OAuth2Server;
use OAuth2\OpenID\GrantType\AuthorizationCode;
use OAuth2\GrantType\UserCredentials;
use OAuth2\Storage\UserCredentialsInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\GrantType\RefreshToken;
use Micro\Auth\Auth;
use Balloon\App\Idp\Auth\Token;
use Balloon\App\Idp\GrantType\UserCredentialsMultiFactor;
use Balloon\App\Idp\Storage\UserCredentials as UserCredentialsStorage;
use Balloon\App\Idp\Storage\UserCredentialsMultiFactor as UserCredentialsMultiFactorStorage;
use Balloon\Hook;
use Balloon\App\Idp\Hook\MultiFactorAuth;
use Balloon\Server;

return [
    OAuth2Server::class => [
        'arguments' => [
            'storage' => '{'.UserCredentialsStorage::class.'}',
            'config' => [
                'enforce_state' => true,
                'allow_implicit' => true,
                'use_openid_connect' => true,
                'issuer' => 'balloon'
            ],
            'grantTypes' => [
                'user_credentials' => '{'.UserCredentials::class.'}',
                'user_credentials_mfa' => '{'.UserCredentialsMultiFactor::class.'}',
                'refresh_token' => '{'.RefreshToken::class.'}',
            ]
        ],
        'services' => [
            /*UserCredentialsInterface::class => [
                'use' => UserCredentialsStorage::class,
                'merge' => false,
            ],*/
            RefreshTokenInterface::class => [
                'use' => UserCredentialsStorage::class,
            ],
            UserCredentials::class => [
                'arguments' => [
                    'storage' => '{'.UserCredentialsStorage::class.'}'
                ]
            ],
            UserCredentialsMultiFactor::class => [
                'arguments' => [
                    'storage' => '{'.UserCredentialsMultiFactorStorage::class.'}'
                ]
            ],
            UserCredentialsMultiFactorStorage::class => [
                'merge' => false
            ],
            Server::class => [
                'singleton' => false
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
    Hook::class => [
        'calls' => [
            MultiFactorAuth::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.MultiFactorAuth::class.'}']
            ],
        ],
    ],
];
