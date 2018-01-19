<?php
use Balloon\App;
use Balloon\Auth\Adapter\Basic\Db;
use Balloon\Converter;
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
use Balloon\Migration\Delta\QueueToCappedCollection;
use Balloon\Migration\Delta\JsonEncodeFilteredCollection;
use Balloon\Migration\Delta\v1AclTov2Acl;
use Balloon\Migration\Delta\ShareName;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mail\Transport\Sendmail;
use Balloon\Hook\Delta;
use Balloon\Hook\AutoDestroy;
use Balloon\Hook\CleanTrash;

return [
    Client::class => [
        'arguments' => [
            'uri' => 'mongodb://localhost:27017',
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
        'calls' => [
            'file' => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{'.StreamHandler::class.'}']
            ],
        ],
        'services' => [
            StreamHandler::class => [
                'arguments' => [
                    'stream' => '/tmp/my_app.log',
                    'level' => 100
                ]
            ]
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
    Migration::class => [
        'calls' => [
            CoreInstallation::class => [
                'method' => 'injectDelta',
                'arguments' => ['delta' => '{'.CoreInstallation::class.'}']
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
        'use' => Sendmail::class
    ]
];
