<?php
use Balloon\App;
use Balloon\Auth\Adapter\Basic\Db;
use Balloon\Converter;
use Balloon\Converter\Adapter\ImagickImage;
use Balloon\Converter\Adapter\Office;
use Balloon\Exception;
use Balloon\Filesystem\Storage;
use Balloon\Filesystem\Storage\Adapter\Gridfs;
use Balloon\Hook;
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
use Balloon\Console;
use Balloon\Console\Upgrade;
use Balloon\Console\Jobs;
use Balloon\Console\Useradd;
use Balloon\Console\Usermod;
use Balloon\Console\Groupadd;
use Balloon\Console\Groupmod;
use Balloon\Migration;
use Balloon\Migration\Delta\CoreInstallation;
use Balloon\Migration\Delta\FileToStorageAdapter;
use Balloon\Migration\Delta\LdapGroupsToLocalGroups;
use Balloon\Migration\Delta\QueueToCappedCollection;
use Balloon\Migration\Delta\JsonEncodeFilteredCollection;
use Balloon\Migration\Delta\v1AclTov2Acl;
use Balloon\Migration\Delta\UserCreateDate;
use Balloon\Migration\Delta\ShareName;
use Balloon\Migration\Delta\HexColorToGenericName;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mail\Transport\Smtp;
use Balloon\Hook\Delta;
use Balloon\Hook\AutoDestroy;
use Balloon\Hook\CleanTrash;

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
            Delta::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Delta::class.'}']
            ],
            AutoDestroy::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.AutoDestroy::class.'}']
            ],
            CleanTrash::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.CleanTrash::class.'}']
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
            LdapGroupsToLocalGroups::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.LdapGroupsToLocalGroups::class.'}']
            ],
            FileToStorageAdapter::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.FileToStorageAdapter::class.'}']
            ],
            QueueToCappedCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.QueueToCappedCollection::class.'}']
            ],
            JsonEncodeFilteredCollection::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.JsonEncodeFilteredCollection::class.'}']
            ],
            v1AclTov2Acl::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.v1AclTov2Acl::class.'}']
            ],
            ShareName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.ShareName::class.'}']
            ],
            HexColorToGenericName::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.HexColorToGenericName::class.'}']
            ],
            UserCreatedDate::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.UserCreatedDate::class.'}']
            ],
            CoreInstallation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.CoreInstallation::class.'}']
            ],
        ],
    ],
    Console::class => [
        'calls' => [
            Jobs::class => [
                'method' => 'injectModule',
                'arguments' => ['module' => '{'.Jobs::class.'}', 'name' => 'jobs']
            ],
            Upgrade::class => [
                'method' => 'injectModule',
                'arguments' => ['module' => '{'.Upgrade::class.'}', 'name' => 'upgrade']
            ],
            Useradd::class => [
                'method' => 'injectModule',
                'arguments' => ['module' => '{'.Useradd::class.'}', 'name' => 'useradd']
            ],
            Usermod::class => [
                'method' => 'injectModule',
                'arguments' => ['module' => '{'.Usermod::class.'}', 'name' => 'usermod']
            ],
            Groupadd::class => [
                'method' => 'injectModule',
                'arguments' => ['module' => '{'.Groupadd::class.'}', 'name' => 'groupadd']
            ],
            Groupmod::class => [
                'method' => 'injectModule',
                'arguments' => ['module' => '{'.Groupmod::class.'}', 'name' => 'groupmod']
            ],
        ]
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
