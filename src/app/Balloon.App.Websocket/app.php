<?php
use Balloon\Bootstrap\Cli as CliBootstrap;
use Balloon\App\Websocket\Constructor\Cli;
use Balloon\App\Websocket\Server;
use Swoole\WebSocket\Server as SwooleServer;
use FastRoute\RouteCollector;
use Balloon\App\CoreApiv3\v3\Models;

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
            'port' => '{ENV(BALLOON_WEBSOCKET_PORT,80)(int)}',
            'mode' => SWOOLE_PROCESS,
            'sock_type' => SWOOLE_SOCK_TCP
        ]
    ],
    Server::class => [
        'calls' => [
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'processes',
                    'collection' => 'taskscheduler.jobs',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\ProcessFactory::class)->decorate($this->get(\Balloon\Process\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'users',
                    'collection' => 'users',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\UserFactory::class)->decorate($this->get(\Balloon\User\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'groups',
                    'collection' => 'groups',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\GroupFactory::class)->decorate($this->get(\Balloon\Group\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'nodes',
                    'collection' => 'nodes',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\NodeFactory::class)->decorate($this->get(\Balloon\Node\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
        ]
    ]
];
