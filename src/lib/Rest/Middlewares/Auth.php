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

class Auth implements MiddlewareInterface
{
    /**
     * Auth.
     *
     * @var CoreAuth
     */
    protected $auth;

    /**
     * User factory.
     *
     * @var UserFactory
     */
    protected $user_factory;

    /**
     * Init.
     */
    public function __construct(CoreAuth $auth, Emitter $emitter, UserFactory $user_factory)
    {
        $this->auth = $auth;
        $this->emitter = $emitter;
        $this->user_factory = $user_factory;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $target = $request->getRequestTarget();

        $this->emitter->emit('http.stack.preAuth', $request);

        if (preg_match('#^/spec/#', $target)) {
            return $handler->handle($request);
        }

        $attributes = $request->getAttributes();
        if (!isset($attributes['identity']) && $identity = $this->auth->requireOne($request)) {
            $this->emitter->emit('http.stack.postAuth', $request, $identity);
            $request = $request->withAttribute('identity', $this->user_factory->build($identity->getRawAttributes()));
        }

        return $handler->handle($request);
    }
}
