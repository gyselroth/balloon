<?php
use Balloon\App;
use Balloon\Auth\Adapter\Basic\Db;
use Balloon\Converter;
use Balloon\Converter\Adapter\ImagickImage;
use Balloon\Converter\Adapter\Office;
use Balloon\Exception;
use Balloon\Filesystem\Storage\Adapter\Gridfs;
use Composer\Autoload\ClassLoader as Composer;
use Micro\Auth\Auth;
use Micro\Config\Config;
use Micro\Container\Container;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Balloon\App\Notification\Notification;
use Balloon\Migration;
use Balloon\Migration\Delta;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mail\Transport\Smtp;
use Balloon\Hook;
use Balloon\Async\WorkerFactory;
use TaskScheduler\Queue;
use TaskScheduler\WorkerFactoryInterface;
use TaskScheduler\WorkerManager;
use Balloon\Filesystem\Node\Factory as NodeFactory;
use Balloon\Filesystem\Storage\Adapter\Gridfs as GridfsStorage;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface as StorageAdapterInterface;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\HiddenString;

return [
    Client::class => [
        'arguments' => [
            'uri' => '{ENV(BALLOON_MONGODB_URI,mongodb://localhost:27017)}',
            'driverOptions' => [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ]
            ]
        ],
    ],
    NodeFactory::class => [
        'services' => [
            StorageAdapterInterface::class => [
                'use' => GridfsStorage::class
            ]
        ]
    ],
    EncryptionKey::class => [
        'use' => KeyFactory::class,
        'factory' => 'importEncryptionKey',
        'arguments' => [
            'keyData' => '{'.HiddenString::class.'}'
        ],
        'services' => [
            HiddenString::class => [
                'arguments' => [
                    'value' => "{ENV(BALLOON_ENCRYPTION_KEY,3140040033da9bd0dedd8babc8b89cda7f2132dd5009cc43c619382863d0c75e172ebf18e713e1987f35d6ea3ace43b561c50d9aefc4441a8c4418f6928a70e4655de5a9660cd323de63b4fd2fb76525470f25311c788c5e366e29bf60c438c4ac0b440e)}"
                ]
            ]
        ]
    ],
    Database::class => [
        'use' => '{MongoDB\Client}',
        'calls' => [[
            'method' => 'selectDatabase',
            'arguments' => [
                'databaseName' => 'balloon'
            ],
            'select' => true
        ]]
    ],
    Queue::class => [
        'services' => [
            WorkerFactoryInterface::class => [
                'use' => WorkerFactory::class
            ]
        ]
    ],
    WorkerManager::class => [
        'services' => [
            WorkerFactoryInterface::class => [
                'use' => WorkerFactory::class
            ]
        ]
    ],
    LoggerInterface::class => [
        'use' => Logger::class,
        'arguments' => [
            'name' => 'default'
        ],
        'calls' => [
            'stderr' => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{stderr}']
            ],
            'stdout' => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{stdout}']
            ],
        ],
        'services' => [
            'Monolog\Formatter\FormatterInterface' => [
                'use' => 'Monolog\Formatter\LineFormatter',
                'arguments' => [
                    'dateFormat' => 'Y-d-m H:i:s',
                    'format' => "%datetime% [%context.category%,%level_name%]: %message% %context.params% %context.exception%\n"
                ],
                'calls' => [
                    ['method' => 'includeStacktraces']
                ]
            ],
            'stderr' => [
                'use' => 'Monolog\Handler\StreamHandler',
                'arguments' => [
                    'stream' => 'php://stderr',
                    'level' => 600,
                ],
                'calls' => [
                    'formatter' => [
                        'method' => 'setFormatter'
                    ]
                ],
            ],
            'stdout' => [
                'use' => 'Monolog\Handler\FilterHandler',
                'arguments' => [
                    'handler' => '{output}',
                    'minLevelOrList' => 100,
                    'maxLevel' => 550
                ],
                'services' => [
                    'output' => [
                        'use' => 'Monolog\Handler\StreamHandler',
                        'arguments' => [
                            'stream' => 'php://stdout',
                            'level' => 100
                        ],
                        'calls' => [
                            'formatter' => [
                                'method' => 'setFormatter'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    Converter::class => [
        'calls' => [
            ImagickImage::class => [
                    'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.ImagickImage::class.'}']
            ],
            /*Office::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Office::class.'}']
            ],*/
        ]
    ],
    Hook::class => [
        'calls' => [
            Hook\Delta::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\Delta::class.'}']
            ],
            Hook\AutoDestroy::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\AutoDestroy::class.'}']
            ],
            Hook\CleanTrash::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\CleanTrash::class.'}']
            ],
            Hook\CleanTempStorage::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\CleanTempStorage::class.'}']
            ],
            Hook\ExternalStorage::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Hook\ExternalStorage::class.'}']
            ],
        ]
    ],
    Migration::class => [
        'calls' => [
            Delta\LdapGroupsToLocalGroups::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\LdapGroupsToLocalGroups::class.'}']
            ],
            Delta\Md5BlobIgnoreNull::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\Md5BlobIgnoreNull::class.'}']
            ],
            Delta\FileToStorageAdapter::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\FileToStorageAdapter::class.'}']
            ],
            Delta\QueueToCappedCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\QueueToCappedCollection::class.'}']
            ],
            Delta\JsonEncodeFilteredCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\JsonEncodeFilteredCollection::class.'}']
            ],
            Delta\v1AclTov2Acl::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\v1AclTov2Acl::class.'}']
            ],
            Delta\ShareName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\ShareName::class.'}']
            ],
            Delta\HexColorToGenericName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\HexColorToGenericName::class.'}']
            ],
            Delta\UserCreatedDate::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\UserCreatedDate::class.'}']
            ],
            Delta\HistoryToFileStorageAdapter::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\HistoryToFileStorageAdapter::class.'}']
            ],
            Delta\AddHashToHistory::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\AddHashToHistory::class.'}']
            ],
            Delta\GridfsFlatReferences::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\GridfsFlatReferences::class.'}']
            ],
            Delta\RemoveStorageAdapterName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\RemoveStorageAdapterName::class.'}']
            ],
            Delta\SetStorageReferenceToNull::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\SetStorageReferenceToNull::class.'}']
            ],
            Delta\RemoveStorageReferenceFromHistory::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\RemoveStorageReferenceFromHistory::class.'}']
            ],
            Delta\SetPointerId::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\SetPointerId::class.'}']
            ],
            Delta\CreateUniqueNodeIndex::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\CreateUniqueNodeIndex::class.'}']
            ],
            Delta\Postv1Cleanup::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\Postv1Cleanup::class.'}']
            ],
            Delta\CoreInstallation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\CoreInstallation::class.'}']
            ],
        ],
    ],
    Auth::class => [
        'calls' => [
            'basic_db' => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Db::class.'}', 'name' => 'basic_db']
            ],
        ],
    ],
    TransportInterface::class => [
        'use' => Smtp::class
    ],
];
