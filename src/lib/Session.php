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
}
