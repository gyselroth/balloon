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
    ],
    Migration::class => [
        'calls' => [
            CoreInstallation::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.CoreInstallation::class.'}']
            ],
            FileToStorageAdapter::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.FileToStorageAdapter::class.'}']
            ],
            QueueToCappedCollection::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.QueueToCappedCollection::class.'}']
            ],
            JsonEncodeFilteredCollection::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.JsonEncodeFilteredCollection::class.'}']
            ],
            v1AclTov2Acl::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.v1AclTov2Acl::class.'}']
            ],
            ShareName::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.ShareName::class.'}']
            ],
        ],
    ],
    Console::class => [
        'calls' => [
            Jobs::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Jobs::class.'}', 'name' => 'jobs']
            ],
            Upgrade::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Upgrade::class.'}', 'name' => 'upgrade']
            ],
            Useradd::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Useradd::class.'}', 'name' => 'useradd']
            ],
            Usermod::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Usermod::class.'}', 'name' => 'usermod']
            ],
            Groupadd::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Groupadd::class.'}', 'name' => 'groupadd']
            ],
            Groupmod::class => [
                'method' => 'injectAdapter',
                'arguments' => ['adapter' => '{'.Groupmod::class.'}', 'name' => 'groupmod']
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
    App::class => [
        'adapter' => []
    ],
    TransportInterface::class => [
        'use' => Sendmail::class
    ]
];
