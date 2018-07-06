<?php
use Balloon\App;
use Balloon\Auth\Adapter\Basic\Db;
use Balloon\Converter;
use Balloon\Converter\Adapter\ImagickImage;
use Balloon\Converter\Adapter\Office;
use Balloon\Exception;
use Balloon\Filesystem\Storage;
use Balloon\Filesystem\Storage\Adapter\Gridfs;
use Balloon\Server;
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
    Database::class => [
        'use' => '{MongoDB\Client}',
        'selects' => [[
            'method' => 'selectDatabase',
            'arguments' => [
                'databaseName' => 'balloon'
            ]
        ]]
    ],
    LoggerInterface::class => [
        'use' => Logger::class,
        'arguments' => [
            'name' => 'default'
        ],
        'calls' => [
            'file' => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{file}']
            ],
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
            'file' => [
                'use' => 'Monolog\Handler\StreamHandler',
                'arguments' => [
                    'stream' => '{ENV(BALLOON_LOG_DIR,/tmp)}/out.log',
                    'level' => 100
                 ],
                'calls' => [
                    'formatter' => [
                        'method' => 'setFormatter'
                    ]
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
            Office::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Office::class.'}']
            ],
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
        ]
    ],
    Storage::class => [
        'calls' => [
            'gridfs' => [
                'method' => 'injectAdapter',
                'arguments' => [
                    'adapter' => '{'.Gridfs::class.'}',
                    'name' => 'gridfs'
                ]
            ]
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
            Delta\AddHashToHistory::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\AddHashToHistory::class.'}']
            ],
            Delta\GridfsFlatReferences::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.Delta\GridfsFlatReferences::class.'}']
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
