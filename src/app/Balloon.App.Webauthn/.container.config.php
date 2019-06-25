<?php
use Balloon\Migration;
use Balloon\App\Webauthn\Migration\Delta\Installation;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\App\Webauthn\Constructor\Http;
use Balloon\App\Webauthn\Storage\MongoDB;
use OAuth2\Server as OAuth2Server;
use OAuth2\OpenID\GrantType\AuthorizationCode;
use Balloon\App\Webauthn\GrantType\Webauthn;
use Balloon\App\Webauthn\GrantType\WebauthnMfa;
use OAuth2\Storage\UserCredentialsInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\GrantType\RefreshToken;
use Micro\Auth\Auth;
use Balloon\App\Webauthn\Auth\Token;
use Balloon\App\Webauthn\GrantType\UserCredentialsMultiFactor;
use Balloon\App\Webauthn\Storage\Db as DbStorage;
use Balloon\Hook;
use Balloon\App\Webauthn\Hook\MultiFactorAuth;
use Balloon\Server;
use Webauthn\AuthenticationExtensions\AuthenticationExtension;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use CBOR\Decoder;
use CBOR\OtherObject\OtherObjectManager;
use CBOR\Tag\TagObjectManager;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\Request;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AndroidSafetyNetAttestationStatementSupport;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Webauthn\CredentialRepository;
use Balloon\App\Webauthn\CredentialRepository as BalloonCredentialRepository;
use Webauthn\TokenBinding\TokenBindingHandler;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Webauthn' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    OAuth2Server::class => [
        'arguments' => [
            'grantTypes' => [
                'user_credentials_webauthn' => '{'.Webauthn::class.'}',
                'user_credentials_webauthn_mfa' => '{'.WebauthnMfa::class.'}',
            ]
        ],
    ],
    Migration::class => [
        'calls' => [
            Installation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Installation::class.'}']
            ],
        ]
    ],
    AuthenticationExtensionsClientInputs::class => [
        'calls' => [
            'loc' => [
                'method' => 'add',
                'arguments' => [
                    'extension' => '{'.AuthenticationExtension::class.'}'
                ]
            ]
        ],
        'services' => [
            AuthenticationExtension::class => [
                'arguments' => [
                    'name' => 'loc',
                    'value' => true
                ]
            ]
        ]
    ],
    Manager::class => [
        'calls' => [
            ECDSA\ES256::class => [
                'method' => 'add',
                'arguments' => [
                    'algorithm' => '{'.ECDSA\ES256::class.'}'
                ]
            ],
            ECDSA\ES512::class => [
                'method' => 'add',
                'arguments' => [
                    'algorithm' => '{'.ECDSA\ES512::class.'}'
                ]
            ],
            EdDSA\EdDSA::class => [
                'method' => 'add',
                'arguments' => [
                    'algorithm' => '{'.EdDSA\EdDSA::class.'}'
                ]
            ],
            RSA\RS1::class => [
                'method' => 'add',
                'arguments' => [
                    'algorithm' => '{'.RSA\RS1::class.'}'
                ]
            ],
            RSA\RS256::class => [
                'method' => 'add',
                'arguments' => [
                    'algorithm' => '{'.RSA\RS256::class.'}'
                ]
            ],
            RSA\RS512::class => [
                'method' => 'add',
                'arguments' => [
                    'algorithm' => '{'.RSA\RS512::class.'}'
                ]
            ]
        ],
    ],
    AttestationStatementSupportManager::class => [
        'singleton' => false,
        'calls' => [
            NoneAttestationStatementSupport::class => [
                'method' => 'add',
                'arguments' => [
                    'attestationStatementSupport' => '{'.NoneAttestationStatementSupport::class.'}'
                ]
            ],
            FidoU2FAttestationStatementSupport::class => [
                'method' => 'add',
                'arguments' => [
                    'attestationStatementSupport' => '{'.FidoU2FAttestationStatementSupport::class.'}'
                ]
            ],
            AndroidSafetyNetAttestationStatementSupport::class => [
                'method' => 'add',
                'arguments' => [
                    'attestationStatementSupport' => '{'.AndroidSafetyNetAttestationStatementSupport::class.'}'
                ]
            ],
            TPMAttestationStatementSupport::class => [
                'method' => 'add',
                'arguments' => [
                    'attestationStatementSupport' => '{'.TPMAttestationStatementSupport::class.'}'
                ]
            ],
            PackedAttestationStatementSupport::class => [
                'method' => 'add',
                'arguments' => [
                    'attestationStatementSupport' => '{'.PackedAttestationStatementSupport::class.'}'
                ]
            ],
        ],
        'services' => [
            AndroidSafetyNetAttestationStatementSupport::class => [
                'arguments' => [
                    'apiKey' => 'xxx'
                ],
            ]
        ]
    ],
    CredentialRepository::class => [
        'use' => BalloonCredentialRepository::class
    ],
    TokenBindingHandler::class => [
        'use' => TokenBindingNotSupportedHandler::class
    ],
];
