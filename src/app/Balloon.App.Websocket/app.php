<?php
use Balloon\Bootstrap\Cli as CliBootstrap;
use Balloon\App\Websocket\Constructor\Cli;
use Balloon\App\Websocket\Server;
use Swoole\WebSocket\Server as SwooleServer;

return [
    CliBootstrap::class => [
        'calls' => [
            'Balloon.App.Websocket' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Cli::class.'}']
            ],
        ]
    ],
    SwooleServer::class => [
        'arguments' => [
            'host' => '{ENV(BALLOON_WEBSOCKET_HOST,0.0.0.0)}',
            'port' => 9501,
            'mode' => SWOOLE_PROCESS,
            'sock_type' => SWOOLE_SOCK_TCP
        ]
    ],
    Server::class => [
        'calls' => [
            [
                'method' => 'addHandler',
                'arguments' => [
                    'collection' => 'taskscheduler.jobs',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(\Balloon\App\Api\v2\Models\ProcessFactory::class)->decorate($this->get(\Balloon\Process\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'collection' => 'users',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(\Balloon\App\Api\v2\Models\UserFactory::class)->decorate($this->get(\Balloon\User\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'collection' => 'groups',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(\Balloon\App\Api\v2\Models\GroupFactory::class)->decorate($this->get(\Balloon\Group\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'collection' => 'nodes',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(\Balloon\App\Api\v2\Models\NodeFactory::class)->decorate($this->get(\Balloon\Node\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
        ]
    ]
];
