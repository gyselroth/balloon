<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Websocket;

use Balloon\Hook;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;
use TaskScheduler\Queue;
use TaskScheduler\Scheduler;
use League\Event\Emitter;
use MongoDB\Database;
use Balloon\User\Factory as UserFactory;
use Closure;
use Balloon\User\UserInterface;
use Swoole\Process;
use Micro\Auth\Auth;
use Swoole\WebSocket\Server as SwooleServer;

class Server
{
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
     * Constructor.
     */
    public function __construct(SwooleServer $server, Auth $auth, Database $db, Emitter $emitter, UserFactory $user_factory, LoggerInterface $logger)
    {
        $this->auth = $auth;
        $this->server = $server;
        $this->auth = $auth;
        $this->db = $db;
        $this->emitter = $emitter;
        $this->logger = $logger;
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

        $this->server->on('open', function (\Swoole\Websocket\Server $server, $request) use($process) {
            $this->logger->info('handshake success with fd [fd]', [
                'category' => get_class($this),
                'fd' => $request->fd,
            ]);

            $server->push($request->fd, '{"kind":"Status","message":"Welcome to the balloon WebSocket server. You have 60s to authenticate, otherwise your connection gets closed."}');

            $fd = $request->fd;
            \Swoole\Timer::after(60000, function() use($fd, $process) {
                if(!isset($this->pool[$fd])) {
                    $this->logger->info('connection [fd] auth timeout reached, close connection', [
                        'category' => get_class($this),
                        'fd' => $fd,
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

            $message = json_decode($frame->data, true);
            if($message === null || !isset($message['op']) || !isset($message['data'])) {
                return $server->push($frame->fd, '{"kind":"Error","message":"Invalid payload received"}');
            }

            switch($message['op']) {
                case 'auth':
                    $server->push($frame->fd, $this->authClient($process, $frame->fd, $message));
                break;
                default:
                    $server->push($frame->fd, '{"kind":"Error","message":"Unknown operation received"}');
            }
        });

        $this->server->on('close', function ($ser, $fd) use($process) {
            $this->logger->debug('client [{fd}] closed connection', [
                'category' => get_class($this),
                'fd' => $fd,
            ]);

            $process->push(serialize(['op' => 'del', 'fd' => $fd]));
            unset($this->pool[$fd]);
        });

        $this->server->addProcess($process);
        $process->start();

        $this->server->start();

        return true;
    }

    /**
     * Auth
     */
    protected function authClient(Process $process, $fd, array $auth): string
    {
        //create dummy http request with http authorization information
        $request  = new \Zend\Diactoros\ServerRequest();
        $request = $request->withHeader('Authorization', $auth['data']);

        try {
            $identity = $this->auth->requireOne($request);
            $user = $this->user_factory->build($identity->getRawAttributes());
            $this->pool[$fd] = $user;
            $process->push(serialize(['op' => 'add', 'fd' => $fd, 'user' => $user]));

            return '{"kind":"Sucess","message":"Connection authenticated"}';
        } catch(\Exception $e) {
            return '{"kind":"Error","message":"'.$e->getMessage().'"}';
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
        ]);

        $changeStream->rewind();

        while (true) {
            if($data = $process->pop()) {
                $data = unserialize($data);

                switch($data['op']) {
                    case 'add':
                        $this->pool[$data['fd']] = $data['user'];
                    break;
                    case 'del':
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
    public function addHandler(string $collection, Closure $handler): self
    {
        $this->handler[$collection] = $handler;
        return $this;
    }

    /**
     * Decorate event and push to clients
     */
    public function handle($client, UserInterface $user, $event, $request)
    {
        try {
            $body = [
                $event['operationType'],
                $this->handler[$event['ns']['coll']]($user, $event['fullDocument'], $request)
            ];

            $this->server->push($client, json_encode($body));
        } catch(\Exception $e) {
            $this->logger->error('exception occurred during resource handling, skip push client [fd]', [
                'category' => get_class($this),
                'exception' => $e,
                'fd' => $client,
            ]);
        }
    }

    /**
     * Publis event
     */
    protected function publish(array $event)
    {
        $request  = new \Zend\Diactoros\ServerRequest();
        foreach($this->server->connections as $client) {
            if(!isset($this->pool[$client])) {
                $this->logger->debug('skip message for unauthenticated connection [{fd}]', [
                    'category' => get_class($this),
                    'fd' => $client,
                ]);

                continue;
            }

            $user = $this->pool[$client];
            $this->handle($client, $user, $event, $request);
        }

    }
}
