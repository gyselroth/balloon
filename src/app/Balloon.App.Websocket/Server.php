<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Websocket;

use Psr\Log\LoggerInterface;
use League\Event\Emitter;
use MongoDB\Database;
use Balloon\User\Factory as UserFactory;
use Closure;
use Balloon\User\UserInterface;
use Swoole\Process;
use Swoole\Timer;
use Micro\Auth\Auth;
use Swoole\WebSocket\Server as SwooleServer;
use Zend\Diactoros\ServerRequest;
use Balloon\Acl;

class Server
{
    /**
     * Connection/User mapping
     *
     * @var array
     */
    protected $pool = [];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Queue.
     *
     * @var Queue
     */
    protected $queue;

    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Emitter.
     *
     * @var Emitter
     */
    protected $emitter;

    /**
     * Acl
     */
    protected $acl;

    /**
     * Constructor.
     */
    public function __construct(SwooleServer $server, Auth $auth, Database $db, Emitter $emitter, UserFactory $user_factory, Acl $acl, LoggerInterface $logger)
    {
        $this->auth = $auth;
        $this->server = $server;
        $this->auth = $auth;
        $this->db = $db;
        $this->emitter = $emitter;
        $this->logger = $logger;
        $this->acl = $acl;
        $this->user_factory = $user_factory;
    }

    /**
     * Start websocket server
     */
    public function start(): bool
    {
        $this->logger->info('initialize websocket server', [
            'category' => get_class($this),
        ]);

        $process = new Process(function(Process $p) {
            $this->changeStream($p);
        });
        $process->useQueue(1, Process::IPC_NOWAIT);

        $this->server->on('open', function (SwooleServer $server, $request) use($process) {
            $this->logger->info('handshake success with fd [fd]', [
                'category' => get_class($this),
                'fd' => $request->fd,
            ]);

            return $this->send($server, $request->fd, [
                'kind' => 'Status',
                'message' => "Welcome to the balloon WebSocket server. You have 60s to authenticate, otherwise your connection gets closed.",
            ]);

            $fd = $request->fd;
            Timer::after(60000, function() use($fd, $process, $server) {
                if(!isset($this->pool[$fd])) {
                    $this->logger->info('connection [fd] auth timeout reached, close connection', [
                        'category' => get_class($this),
                        'fd' => $fd,
                    ]);

                    return $this->send($server, $fd, [
                        'kind' => 'Status',
                        'message' => "Authentication timeout reached, close connection",
                    ]);

                    $this->server->close($fd);
                }
            });
        });

        $this->server->on('message', function (\Swoole\Websocket\Server $server, $frame) use($process) {
            $this->logger->debug('message received from [{fd}]', [
                'category' => get_class($this),
                'fd' => $frame->fd,
                'opcode' => $frame->opcode,
                'finish' => $frame->finish,
            ]);

            $this->handleMessage($server, $frame, $process);
        });

        $this->server->on('close', function ($ser, $fd) use($process) {
            $this->logger->debug('client [{fd}] closed connection', [
                'category' => get_class($this),
                'fd' => $fd,
            ]);

            $process->push(serialize(['action' => 'close', 'fd' => $fd]));
            unset($this->pool[$fd]);
        });

        $this->server->addProcess($process);
        $process->start();

        $this->server->start();

        return true;
    }


   /**
    * Handle message
    */
    protected function handleMessage($server, $frame, $process)
    {
        $message = json_decode($frame->data, true);

        if($message === null || !isset($message['action']) || !isset($message['payload'])) {
            return $this->send($server, $frame->fd, [
                'kind' => 'Error',
                'message' => "Invalid payload received. `action` and `payload` expected.",
            ]);
        }

        switch($message['action']) {
            case 'auth':
                return $this->send($server, $frame->fd, $this->actionAuth($process, $frame->fd, $message['payload']));
            case 'subscribe':
                return $this->send($server, $frame->fd, $this->actionSubscribe($process, $frame->fd, $message['payload']));
            default:
                return $this->send($server, $frame->fd, [
                    'kind' => 'Error',
                    'payload' => 'Unknown action received',
                ]);
        }
    }

    /**
     * Send to client
     */
    protected function send($server, $frame, array $payload)
    {
        $server->push($frame, json_encode($payload));
    }

    /**
     * Action subscribe
     */
    protected function actionSubscribe(Process $process, $fd, string $payload): array
    {
        $mapping = $this->getChannelMapping();
        if(!in_array($payload, $mapping)) {
            return [
                'kind' => 'Error',
                'payload' => 'Channel does not exists',
            ];
        }

        $process->push(serialize(['action' => 'subscribe', 'fd' => $fd, 'payload' => $payload]));

        return [
            'kind' => 'Success',
            'payload' => 'Subscribed to channel',
        ];
    }

    /**
     * Action auth
     */
    protected function actionAuth(Process $process, $fd, string $payload): array
    {
        //create dummy http request with http authorization information
        $request  = new \Zend\Diactoros\ServerRequest();
        $request = $request->withHeader('Authorization', $payload);

        try {
            $identity = $this->auth->requireOne($request);
            $user = $this->user_factory->build($identity->getRawAttributes());
            $this->pool[$fd] = $user;
            $process->push(serialize(['action' => 'auth', 'fd' => $fd, 'payload' => $user]));

            return [
                'kind' => 'Success',
                'payload' => 'Connection authenticated',
            ];
        } catch(\Exception $e) {
            return [
                'kind' => 'Error',
                'payload' => $e->getMessage(),
            ];
        }
    }

    /**
     * Start MongoDB changestream and push events to clients (filtered)
     */
    protected function changeStream(Process $process)
    {
        $changeStream = $this->db->watch([
            [
                '$match' => ['ns.coll' => ['$in' => array_keys($this->handler)]]
            ]
        ], [
            'maxAwaitTimeMS' => 1,
            'batchSize' => 100,
            'fullDocument' => 'updateLookup',
        ]);

        $changeStream->rewind();

        while (true) {
            if($data = $process->pop()) {
                $data = unserialize($data);

                switch($data['action']) {
                    case 'auth':
                        $this->pool[$data['fd']] = [
                            'user' => $data['payload'],
                            'subscriptions' => [],
                        ];
                    break;

                    case 'subscribe':
                        $this->pool[$data['fd']]['subscriptions'][] = $data['payload'];
                    break;

                    case 'unsubscribe':
                        //$this->pool[$data['fd']]['subscriptions'][] = $data['payload'];
                    break;

                    case 'close':
                    default:
                        unset($this->pool[$data['fd']]);
                    break;
                }
            }

            $changeStream->next();
            $event = $changeStream->current();

            if ($event === null) {
                continue;
            }

            $this->logger->debug('push action [{action}] on resource [{resource}] from [{collection}]', [
                'category' => get_class($this),
                'collection' => $event['ns']['coll'],
                'resource' => $event['fullDocument']['_id'],
                'action' => $event['operationType'],
            ]);

            $this->publish($event);
        }
    }

    /**
     * Add new resource handler
     */
    public function addHandler(string $channel, string $collection, Closure $handler): self
    {
        $this->handler[$collection] = [
            'handler' => $handler,
            'channel' => $channel,
        ];

        return $this;
    }

    /**
     * Decorate event and push to clients
     */
    public function handle($client, UserInterface $user, string $channel, $event, $request): bool
    {
        if(!$this->acl->isAllowed($request, $user)) {
            return false;
        }

        try {
            $result = $this->handler[$event['ns']['coll']]['handler']($user, $event['fullDocument'], $request);
            if($result === null) {
                return false;
            }

            $body = [
                'kind' => 'Event',
                'channel' => $channel,
                'action' => $event['operationType'],
                'payload' => $result,
            ];

            $this->server->push($client, json_encode($body));
            return true;
        } catch(\Exception $e) {
            $this->logger->error('exception occurred during resource handling, skip push client [fd]', [
                'category' => get_class($this),
                'exception' => $e,
                'fd' => $client,
            ]);
        }

        return false;
    }

    /**
     * Get channel collection mapping
     */
    protected function getChannelMapping(): array
    {
        $channels = [];
        foreach($this->handler as $collection => $handler) {
            $channels[$collection] = $handler['channel'];
        }

        return $channels;
    }

    /**
     * Publis event
     */
    protected function publish(array $event)
    {
        $mapping = $this->getChannelMapping();

        foreach($this->server->connections as $client) {
            if(!isset($this->pool[$client]['user'])) {
                $this->logger->debug('skip message for unauthenticated connection [{fd}]', [
                    'category' => get_class($this),
                    'fd' => $client,
                ]);

                continue;
            }

            if(!in_array($mapping[$event['ns']['coll']], $this->pool[$client]['subscriptions'])) {
                $this->logger->debug('skip message for unsubscribed channel for connection [{fd}]', [
                    'category' => get_class($this),
                    'fd' => $client,
                ]);

                continue;
            }

            $user = $this->pool[$client]['user'];

            //emulate an http request which then can be used to map resources to an api version and
            //check acl while this is always a readonly GET request (PUB)
            $request  = (new ServerRequest(
                [],
                [],
                '/api/v3/'.$mapping[$event['ns']['coll']].'/'.$event['fullDocument']['_id'],
                'GET'
            ))->withAttribute('identity', $user);

            $this->handle($client, $user, $mapping[$event['ns']['coll']], $event, $request);
        }
    }
}
