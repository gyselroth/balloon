<?php

namespace Balloon\Rest;

use Psr\Http\Message\ServerRequestInterface;
use Balloon\Resource\ResourceInterface;
use Closure;

interface ModelFactoryInterface
{
    public function addAttribute(string $name, Closure $handler);
    public function addEmbedded(string $name, Closure $handler);
    public function decorate(ResourceInterface $resource, ServerRequestInterface $request);
}
