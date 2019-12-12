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
use Balloon\User\Factory as UserFactory;
use MongoDB\BSON\ObjectId;

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
    public function __construct(CoreAcl $acl, UserFactory $user_factory)
    {
        $this->acl = $acl;
        $this->user_factory = $user_factory;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $target = $request->getRequestTarget();
        $identity = $request->getAttribute('identity');
        $query = $request->getQueryParams();

        if($identity === null) {
            return $handler->handle($request);
        }

        $request = $this->acl->isAllowed($request, $identity);

        if(isset($query['as'])) {
            $user = $this->user_factory->getOne(new ObjectId($query['as']));

            $request = $request->withAttribute('identity', $user);
        }

        return $handler->handle($request);
    }
}
