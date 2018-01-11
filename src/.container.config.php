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
use Micro\Log\Log;
use Micro\Log\Adapter\File;
use MongoDB\Client;
use Psr\Log\LoggerInterface;
use Balloon\App\Notification\Notification;
use Balloon\Console;
use Balloon\Console\Upgrade;
use Balloon\Console\Jobs;
use Balloon\Console\Useradd;
use Balloon\Migration;
use Balloon\Migration\Delta\CoreInstallation;
use Balloon\Migration\Delta\FileToStorageAdapter;
use Balloon\Migration\Delta\QueueToCappedCollection;
use Balloon\Migration\Delta\JsonEncodeFilteredCollection;
use Balloon\Migration\Delta\v1AclTov2Acl;
use Zend\Mail\Transport\TransportInterface;
use Zend\Mail\Transport\Sendmail;

return [
    'service' => [
        Client::class => [
            'options' => [
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
                            'file' => constant('BALLOON_LOG_DIR').DIRECTORY_SEPARATOR.'out.log',
                            'level' => 10,
                            'date_format' => 'Y-d-m H:i:s',
                            'format' => '[{context.category},{level}]: {message} {context.params} {context.exception}',
                        ],
                    ],
                ],
            ],
        ],
        Hook::class => [
            'adapter' => Hook::DEFAULT_ADAPTER
        ],
        Migration::class => [
            'adapter' => [
                CoreInstallation::class => [],
                FileToStorageAdapter::class => [],
                QueueToCappedCollection::class => [],
                JsonEncodeFilteredCollection::class => [],
                v1AclTov2Acl::class => [],
            ]
        ],
        Console::class => [
            'adapter' => [
                'jobs' => [
                    'use' => Jobs::class
                ],
                'upgrade' => [
                    'use' => Upgrade::class
                ],
                'useradd' => [
                    'use' => Useradd::class
                ],
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
        ],
        TransportInterface::class => [
            'use' => Sendmail::class
        ]
    ]
];
