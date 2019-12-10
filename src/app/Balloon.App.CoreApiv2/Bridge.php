<?php

namespace Balloon\App\CoreApiv2;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

class Bridge
{
    public function __construct(Router $router)
    {
        $this->router = $router;
             $router->appendRoute(new Route('/api/v2/users/{id:#([0-9a-z]{24})#}(/|\z)', v2\Users::class))
             ->appendRoute(new Route('/api/v2/users(/|\z)', v2\Users::class))
             ->appendRoute(new Route('/api/v2/groups/{id:#([0-9a-z]{24})#}(/|\z)', v2\Groups::class))
             ->appendRoute(new Route('/api/v2/groups(/|\z)', v2\Groups::class))
             ->appendRoute(new Route('/api/v2/files/{id:#([0-9a-z]{24})#}(/|\z)', v2\Files::class))
             ->appendRoute(new Route('/api/v2/files(/|\z)', v2\Files::class))
             ->appendRoute(new Route('/api/v2/collections/{id:#([0-9a-z]{24})#}(/|\z)', v2\Collections::class))
             ->appendRoute(new Route('/api/v2/collections(/|\z)', v2\Collections::class))
             ->appendRoute(new Route('/api/v2/nodes/{id:#([0-9a-z]{24})#}(/|\z)', v2\Nodes::class))
             ->appendRoute(new Route('/api/v2/nodes(/|\z)', v2\Nodes::class))
             ->appendRoute(new Route('/api/v2$', v2\Api::class))
             ->appendRoute(new Route('/api/v2', v2\Api::class));

    }

    public function handle(ServerRequestInterface $request)
    {
        $this->router->setPath($request->getRequestTarget());
        $this->router->setContentType($request->getHeader('Content-Type')[0] ?? '');
        $this->router->setVerb($request->getMethod());
        $this->router->run();
    }
}
