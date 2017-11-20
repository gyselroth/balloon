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
use ErrorException;
use Micro\Auth;
use Micro\Config;
use Micro\Container;
use Micro\Log;
use Micro\Log\Adapter\File;
use MongoDB\Client;
use Psr\Log\LoggerInterface;
use Balloon\App\Notification\Notification;
use Balloon\Database;
use Balloon\Database\Delta\CoreInstallation;
use Balloon\Database\Delta\FileToStorageAdapter;
use Balloon\Database\Delta\QueueToCappedCollection;

return [
    Client::class => [
        'options' => [
            'uri' => 'mongodb://localhost:27017',
            'db' => 'balloon',
        ],
    ],
    LoggerInterface::class => [
        'use' => Log::class,
        'adapter' => [
            'file' => [
                'use' => File::class,
                'options' => [
                    'config' => [
                        'file' => APPLICATION_PATH.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR.'out.log',
                        'level' => 10,
                        'date_format' => 'Y-d-m H:i:s',
                        'format' => '[{context.category},{level}]: {message} {context.params} {context.exception}',
                    ],
                ],
            ],
        ],
    ],
    Database::class => [
        'adapter' => [
            CoreInstallation::class => [],
            FileToStorageAdapter::class => [],
            QueueToCappedCollection::class => [],
        ]
    ],
    Auth::class => [
        'adapter' => [
            'basic_db' => [
                'use' => Db::class,
            ],
        ],
    ],
    App::class => [
        'adapter' => []
    ]
];
