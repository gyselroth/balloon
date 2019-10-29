<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

use Balloon\Async\WorkerFactory;
use Balloon\Auth\Adapter\Basic\Db;
use Balloon\Converter;
use Balloon\Converter\Adapter\ImagickImage;
use Balloon\Hook;
use Balloon\Migration;
use Balloon\Migration\Delta;
use Balloon\Node\Factory as NodeFactory;
use Balloon\Rest\Middlewares\Acl as AclMiddleware;
use Balloon\Rest\Middlewares\Auth as AuthMiddleware;
use Balloon\Rest\Middlewares\ExceptionHandler;
use Balloon\Rest\Middlewares\QueryDecoder;
use Balloon\Rest\Middlewares\RequestHandler;
use Balloon\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use Balloon\Storage\Adapter\Gridfs as GridfsStorage;
use Cache\Adapter\Apcu\ApcuCachePool;
use FastRoute\DataGenerator;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\Dispatcher as FastRouteDispatcher;
use FastRoute\Dispatcher\GroupCountBased as FastRouteRoutes;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use FastRoute\RouteParser\Std;
use Lcobucci\ContentNegotiation\ContentTypeMiddleware;
use Lcobucci\ContentNegotiation\Formatter\Json;
use Micro\Auth\Auth;
use Middlewares\AccessLog;
use Middlewares\FastRoute;
use Middlewares\JsonPayload;
use Middlewares\TrailingSlash;
use mindplay\middleman\ContainerResolver;
use mindplay\middleman\Dispatcher;
use Mjelamanov\GuzzlePsr18\Client as GuzzleAdapter;
use MongoDB\Client;
use MongoDB\Database;
use Monolog\Logger;
use Monolog\Processor;
use OpenAPIValidation\PSR15\ValidationMiddleware;
use OpenAPIValidation\PSR15\ValidationMiddlewareBuilder;
use ParagonIE\Halite\HiddenString;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use TaskScheduler\Queue;
use TaskScheduler\WorkerFactoryInterface;
use TaskScheduler\WorkerManager;
use Zend\Mail\Transport\Smtp;
use Zend\Mail\Transport\TransportInterface;

return [
    Client::class => [
        'arguments' => [
            'uri' => '{ENV(BALLOON_MONGODB_URI,mongodb://localhost:27017)}',
            'driverOptions' => [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ],
            ],
        ],
    ],
    Dispatcher::class => [
        'arguments' => [
            'stack' => [
                '{'.ContentTypeMiddleware::class.'}',
                '{'.AccessLog::class.'}',
                '{'.ExceptionHandler::class.'}',
                '{'.AuthMiddleware::class.'}',
                '{'.JsonPayload::class.'}',
//                '{'.ValidationMiddleware::class.'}',
                '{'.ContentTypeMiddleware::class.'}',
                '{'.QueryDecoder::class.'}',
                '{'.FastRoute::class.'}',
                '{'.AclMiddleware::class.'}',
                '{'.TrailingSlash::class.'}',
                '{'.RequestHandler::class.'}',
            ],
            'resolver' => '{'.ContainerResolver::class.'}',
        ],
        'services' => [
            ValidationMiddleware::class => [
                'use' => ValidationMiddlewareBuilder::class,
                'calls' => [
                    [
                        'method' => 'fromYamlFile',
                        'arguments' => ['yamlFile' => realpath('../../api/openapi.yml')],
                    ],
                    [
                        'method' => 'getValidationMiddleware',
                        'select' => true,
                    ],
                ],
            ],
            ContentTypeMiddleware::class => [
                'factory' => 'fromRecommendedSettings',
                'arguments' => [
                    'formats' => [
                        'json' => [
                            'extension' => ['json'],
                            'mime-type' => ['application/json', 'text/json', 'application/x-json'],
                            'charset' => true,
                        ],
                    ],
                    'formatters' => [
                       'application/json' => '{'.Json::class.'}',
                    ],
                ],
            ],
        ],
    ],
    RouteCollector::class => [
        'services' => [
            RouteParser::class => [
                'use' => Std::class,
            ],
            DataGenerator::class => [
                'use' => GroupCountBased::class,
            ],
        ],
    ],
    FastRouteDispatcher::class => [
        'use' => FastRouteRoutes::class,
        'arguments' => [
            'data' => '{routes}',
        ],
        'services' => [
            'routes' => [
                'use' => '{'.RouteCollector::class.'}',
                'calls' => [
                    [
                        'method' => 'getData',
                        'select' => true,
                    ],
                ],
            ],
        ],
    ],
    ClientInterface::class => [
        'use' => GuzzleAdapter::class,
    ],
    CacheInterface::class => [
        'use' => ApcuCachePool::class,
    ],
    StorageAdapterInterface::class => [
        'use' => GridfsStorage::class,
    ],
    Balloon\Collection\Factory::class => [
        'calls' => [
            [
                'method' => 'setNodeFactory',
                'arguments' => ['node_factory' => '{'.Balloon\Node\Factory::class.'}']
            ]
        ]
    ],
    EncryptionKey::class => [
        'use' => KeyFactory::class,
        'factory' => 'importEncryptionKey',
        'arguments' => [
            'keyData' => '{'.HiddenString::class.'}',
        ],
        'services' => [
            HiddenString::class => [
                'arguments' => [
                    'value' => '{ENV(BALLOON_ENCRYPTION_KEY,3140040033da9bd0dedd8babc8b89cda7f2132dd5009cc43c619382863d0c75e172ebf18e713e1987f35d6ea3ace43b561c50d9aefc4441a8c4418f6928a70e4655de5a9660cd323de63b4fd2fb76525470f25311c788c5e366e29bf60c438c4ac0b440e)}',
                ],
            ],
        ],
    ],
    Database::class => [
        'use' => '{MongoDB\Client}',
        'calls' => [[
            'method' => 'selectDatabase',
            'arguments' => [
                'databaseName' => 'balloon',
            ],
            'select' => true,
        ]],
    ],
    Queue::class => [
        'services' => [
            WorkerFactoryInterface::class => [
                'use' => WorkerFactory::class,
            ],
        ],
    ],
    WorkerManager::class => [
        'services' => [
            WorkerFactoryInterface::class => [
                'use' => WorkerFactory::class,
            ],
        ],
    ],
    LoggerInterface::class => [
        'use' => Logger::class,
        'arguments' => [
            'name' => 'default',
            'processors' => [
                '{'.Processor\PsrLogMessageProcessor::class.'}',
            ],
        ],
        'calls' => [
            'stderr' => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{stderr}'],
            ],
            'stdout' => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{stdout}'],
            ],
        ],
        'services' => [
            'Monolog\Formatter\FormatterInterface' => [
                'use' => 'Monolog\Formatter\LineFormatter',
                'arguments' => [
                    'dateFormat' => 'Y-m-d H:i:s',
                    'format' => "%datetime% [%context.category%,%level_name%]: %message% %context% %extra%\n",
                ],
                'calls' => [
                    ['method' => 'includeStacktraces'],
                ],
            ],
            'stderr' => [
                'use' => 'Monolog\Handler\StreamHandler',
                'arguments' => [
                    'stream' => 'php://stderr',
                    'level' => 600,
                ],
                'calls' => [
                    'formatter' => [
                        'method' => 'setFormatter',
                    ],
                ],
            ],
            'stdout' => [
                'use' => 'Monolog\Handler\FilterHandler',
                'arguments' => [
                    'handler' => '{output}',
                    'minLevelOrList' => 100,
                    'maxLevel' => 550,
                ],
                'services' => [
                    'output' => [
                        'use' => 'Monolog\Handler\StreamHandler',
                        'arguments' => [
                            'stream' => 'php://stdout',
                            'level' => 100,
                        ],
                        'calls' => [
                            'formatter' => [
                                'method' => 'setFormatter',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    Converter::class => [
        'calls' => [
            ImagickImage::class => [
                    'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.ImagickImage::class.'}'],
            ],
        ],
    ],
    Hook::class => [
        'calls' => [
            Hook\Delta::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\Delta::class.'}'],
            ],
            Hook\AutoDestroy::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\AutoDestroy::class.'}'],
            ],
            Hook\CleanTrash::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\CleanTrash::class.'}'],
            ],
            Hook\CleanTempStorage::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\CleanTempStorage::class.'}'],
            ],
            Hook\ExternalStorage::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\ExternalStorage::class.'}'],
            ],
        ],
    ],
    Migration::class => [
        'calls' => [
            Delta\CreateUniqueUserMailIndex::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\CreateUniqueUserMailIndex::class.'}'],
            ],
            Delta\LdapGroupsToLocalGroups::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\LdapGroupsToLocalGroups::class.'}'],
            ],
            Delta\Md5BlobIgnoreNull::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\Md5BlobIgnoreNull::class.'}'],
            ],
            Delta\FileToStorageAdapter::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\FileToStorageAdapter::class.'}'],
            ],
            Delta\QueueToCappedCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\QueueToCappedCollection::class.'}'],
            ],
            Delta\JsonEncodeFilteredCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\JsonEncodeFilteredCollection::class.'}'],
            ],
            Delta\v1AclTov2Acl::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\v1AclTov2Acl::class.'}'],
            ],
            Delta\ShareName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\ShareName::class.'}'],
            ],
            Delta\HexColorToGenericName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\HexColorToGenericName::class.'}'],
            ],
            Delta\UserCreatedDate::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\UserCreatedDate::class.'}'],
            ],
            Delta\HistoryToFileStorageAdapter::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\HistoryToFileStorageAdapter::class.'}'],
            ],
            Delta\AddHashToHistory::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\AddHashToHistory::class.'}'],
            ],
            Delta\GridfsFlatReferences::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\GridfsFlatReferences::class.'}'],
            ],
            Delta\RemoveStorageAdapterName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\RemoveStorageAdapterName::class.'}'],
            ],
            Delta\SetStorageReferenceToNull::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\SetStorageReferenceToNull::class.'}'],
            ],
            Delta\RemoveStorageReferenceFromHistory::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\RemoveStorageReferenceFromHistory::class.'}'],
            ],
            Delta\SetPointerId::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\SetPointerId::class.'}'],
            ],
            Delta\CreateUniqueNodeIndex::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\CreateUniqueNodeIndex::class.'}'],
            ],
            Delta\Postv1Cleanup::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\Postv1Cleanup::class.'}'],
            ],
            Delta\CoreInstallation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\CoreInstallation::class.'}', 'priority' => 100],
            ],
        ],
    ],
    Auth::class => [
        'calls' => [
            'basic_db' => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Db::class.'}', 'name' => 'basic_db'],
            ],
        ],
    ],
    TransportInterface::class => [
        'use' => Smtp::class,
    ],
];
