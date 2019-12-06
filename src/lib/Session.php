<?php

declare(strict_types=1);

/**
 * tubee
 *
 * @copyright   Copryright (c) 2017-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use DateTime;
use Generator;
use MongoDB\BSON\ObjectIdInterface;
use Psr\Http\Message\ServerRequestInterface;
use TaskScheduler\JobInterface;
use Balloon\Session\SessionInterface;
use Balloon\Resource\AbstractResource;
use Balloon\Resource\AttributeResolver;
use Balloon\ResourceNamespace\ResourceNamespaceInterface;
use MD5Context;

class Session extends AbstractResource implements SessionInterface
{
    /**
     * Kind.
     */
    public const KIND = 'Session';

    /**
     * Session.
     */
    public function __construct(array $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Get hash context.
     */
    public function getHashContext(): MD5Context
    {
        return unserialize($this->resource['context']);
    }

    /**
     * Finalize hash.
     */
    public function getHash(): string
    {
        if (isset($this->resource['hash'])) {
            return $this->resource['hash'];
        }

        return $this->resource['hash'] = md5_final($this->getHashContext());
    }

    /**
     * Is finalized.
     */
    public function isFinalized(): bool
    {
        return isset($this->resource['hash']);
    }

    /**
     * Get size.
     */
    public function getSize(): int
    {
        return $this->resource['size'] ?? 0;
    }
}
