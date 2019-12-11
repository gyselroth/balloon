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
use Balloon\App\Idp\Storage\Db as DbStorage;
use Balloon\Hook;
use Balloon\App\Idp\Hook\MultiFactorAuth;
use FastRoute\RouteCollector;
use League\Event\Emitter;
use Balloon\App\Idp\Oauth2\Tokens;
use Balloon\User;

return [
    OAuth2Server::class => [
        'arguments' => [
            'storage' => '{'.DbStorage::class.'}',
            'config' => [
                'enforce_state' => true,
                'allow_implicit' => true,
                'use_openid_connect' => true,
                'issuer' => 'balloon',
                'refresh_token_lifetime' => 0,
            ],
            'grantTypes' => [
                'user_credentials' => '{'.UserCredentials::class.'}',
                'user_credentials_mfa' => '{'.UserCredentialsMultiFactor::class.'}',
                'refresh_token' => '{'.RefreshToken::class.'}',
            ]
        ],
        'services' => [
            UserCredentialsInterface::class => [
                'use' => DbStorage::class,
            ],
            RefreshTokenInterface::class => [
                'use' => DbStorage::class,
            ],
            UserCredentials::class => [
                'arguments' => [
                    'storage' => '{'.DbStorage::class.'}'
                ]
            ],
            RefreshToken::class => [
                'arguments' => [
                    'config' => [
                        'unset_refresh_token_after_use' => false,
                    ]
                ]
            ],
            UserCredentialsMultiFactor::class => [
                'arguments' => [
                    'storage' => '{'.DbStorage::class.'}'
                ]
            ],
            Server::class => [
                'singleton' => false
            ]
        ]
    ],
    Emitter::class => [
        'calls' => [[
            'method' => 'addListener',
            'arguments' => [
                'event' => 'http.stack.preAuth',
                'listener' => function($event, &$request) {
                    if($request->getRequestTarget() === '/api/v2/tokens' || $request->getRequestTarget() === '/oidc') {
                        $request = $request->withAttribute('identity', new User([
                            'username' => '',
                        ]));
                    }
                }
            ]
        ]]
    ],
    RouteCollector::class => [
        'calls' => [[
            'method' => 'addRoute',
            'arguments' => [
                'httpMethod',
                'route',
                'handler',
            ],
            'batch' => [
                ['POST', '/oidc', [Tokens::class, 'post']],
                ['POST', '/api/v2/tokens', [Tokens::class, 'post']],
            ]
        ]]
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
            Token::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Token::class.'}']
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
