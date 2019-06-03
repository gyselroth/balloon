<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest\Middlewares;

use Balloon\Acl as CoreAcl;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Acl implements MiddlewareInterface
{
    /**
     * Acl.
     *
     * @var CoreAcl
     */
    protected $acl;

    /**
     * Set the resolver instance.
     */
    public function __construct(CoreAcl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $target = $request->getRequestTarget();

        if ($target === '/api/v1' || $target === '/api') {
            return $handler->handle($request);
        }

        $identity = $request->getAttribute('identity');

        if ($identity === null || $this->acl->isAllowed($request, $identity)) {
            return $handler->handle($request);
        }
    }
}
