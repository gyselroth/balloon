<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest\Middlewares;

use Balloon\User\Factory as UserFactory;
use Micro\Auth\Auth as CoreAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use League\Event\Emitter;
use Micro\Auth\Exception\NotAuthenticated as NotAuthenticatedException;
use Balloon\User\Exception\NotFound as NotFoundException;

class Auth implements MiddlewareInterface
{
    /**
     * User factory.
     *
     * @var UserFactory
     */
    protected $user_factory;

    /**
     * Auth handlers
     *
     * @var array
     */
    protected $handlers = [];

    /**
     * Init.
     */
    public function __construct(Emitter $emitter, UserFactory $user_factory)
    {
        $this->emitter = $emitter;
        $this->user_factory = $user_factory;
    }

    /**
     * Inject an authentication handler for a given route prefix
     */
    public function injectHandler(string $name, string $prefix, CoreAuth $handler) {
        $this->handlers[$name] = [
          'prefix' => $prefix ?? '/',
          'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $target = $request->getRequestTarget();
        $this->emitter->emit('http.stack.preAuth', $request);

        foreach($this->handlers as $auth_handler) {
          if (preg_match('#^'.$auth_handler['prefix'].'#', $target)) {
            $attributes = $request->getAttributes();

            if (!isset($attributes['identity']) && $identity = $auth_handler['handler']->requireOne($request)) {
                $this->emitter->emit('http.stack.postAuth', $request, $identity);

                try {
                  $user = $this->user_factory->getOneByName($identity->getIdentifier());
                } catch(NotFoundException $e) {
                  throw new NotAuthenticatedException("the requested identity has not been found and auto create is disabled", 0, $e);
                }

                $request = $request->withAttribute('identity', $user);
                return $handler->handle($request);
            }
          }
        }

        return $handler->handle($request);
    }
}
