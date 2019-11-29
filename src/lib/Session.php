<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Session\SessionInterface;
use MD5Context;
use MongoDB\BSON\ObjectIdInterface;

class Session implements SessionInterface
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
     * Is finalized.
     */
    public function isFinalized(): bool
    {
        return isset($this->resource['hash']);
    }

    /**
     * Set.
     */
    public function set(array $data): self
    {
        $this->resource = array_merge($this->resource, $data);

        return $this;
    }

    /**
     * Get ID.
     */
    public function getId(): ObjectIdInterface
    {
        return $this->resource['_id'];
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
}
