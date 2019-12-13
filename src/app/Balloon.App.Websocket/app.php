<?php
use Balloon\Bootstrap\Cli as CliBootstrap;
use Balloon\App\Websocket\Constructor\Cli;
use Balloon\App\Websocket\Server;
use Swoole\WebSocket\Server as SwooleServer;
use FastRoute\RouteCollector;
use Balloon\App\CoreApiv3\v3\Models;
use Balloon\Node\Acl;
use Balloon\File;

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
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'sessions',
                    'collection' => 'sessions',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\SessionFactory::class)->decorate($this->get(\Balloon\Session\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'events',
                    'collection' => 'events',
                    'handler' => function($user, $resource, $request) {
                        //Each event must be publish to all clients who also have access to the related node.
                        //This is solved by a dummy node and checked against the node acl itself.
                        $acl = $this->get(Acl::class);
                        $payload = new File([
                            '_id' => $resource['node']['id'],
                            'owner' => $resource['owner'],
                            'shared' => $resource['node']['share'],
                            'reference' => false,
                            'parent' => $resource['node']['parent'],
                        ]);

                        if(!$acl->isAllowed($payload, 'r', $user)) {
                            return null;
                        }

                        return $this->get(Models\EventFactory::class)->decorate($this->get(\Balloon\Event\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                 'arguments' => [
                    'channel' => 'access-roles',
                    'collection' => 'access_roles',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\AccessRolesFactory::class)->decorate($this->get(\Balloon\AccessRole\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
            [
                'method' => 'addHandler',
                'arguments' => [
                    'channel' => 'access-rules',
                    'collection' => 'access_rules',
                    'handler' => function($user, $resource, $request) {
                        return $this->get(Models\AccessRulesFactory::class)->decorate($this->get(\Balloon\AccessRule\Factory::class)->build($resource, $user), $request);
                    }
                ]
            ],
        ]
    ]
];
