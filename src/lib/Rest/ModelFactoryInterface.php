<?php

namespace Balloon\Rest;

use Psr\Http\Message\ServerRequestInterface;
use Balloon\Resource\ResourceInterface;
use Closure;

interface ModelFactoryInterface
{
    /**
     * Add runtime attribute (readonly)
     */
    public function addAttribute(string $name, Closure $handler);


    /**
     * Add embedded resource
     */
    public function addEmbedded(string $name, Closure $handler);


    /**
     * Render attributes from resource with a given request
     */
    public function decorate(ResourceInterface $resource, ServerRequestInterface $request);
}
