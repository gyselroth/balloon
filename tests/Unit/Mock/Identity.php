<?php
namespace Balloon\Testsuite\Unit\Mock;

use \Micro\Auth\Identity as MicroIdentity;
use \Micro\Auth\Adapter\AdapterInterface;
use \Micro\Auth\Adapter\None;
use \Psr\Log\LoggerInterface;

class Identity extends MicroIdentity
{
    private $attributes = [];
    private $identifier;

    public function __construct($identifier, array $attributes=[], LoggerInterface $logger)
    {
        $this->identifier = $identifier;
        $this->attributes = $attributes;
        $this->logger     = $logger;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAdapter(): AdapterInterface
    {
        return new None($this->logger);
    }
}
