<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Mock;

use Micro\Auth\Adapter\AdapterInterface;
use Micro\Auth\Adapter\None;
use Micro\Auth\Identity as MicroIdentity;
use Psr\Log\LoggerInterface;

class Identity extends MicroIdentity
{
    private $attributes = [];
    private $identifier;

    public function __construct($identifier, array $attributes, LoggerInterface $logger)
    {
        $this->identifier = $identifier;
        $this->attributes = $attributes;
        $this->logger = $logger;
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
        return new None();
    }
}
