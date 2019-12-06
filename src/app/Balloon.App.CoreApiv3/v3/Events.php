<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3\v3;

use Balloon\Acl;
use Balloon\Node;
use Balloon\Node\Factory as NodeFactory;
use Balloon\Event\Factory as EventFactory;
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\App\CoreApiv3\v3\Models\EventFactory as EventModelFactory;
use MongoDB\BSON\ObjectId;

class Events
{
    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $event_factory;

    /**
     * Init.
     */
    public function __construct(EventFactory $event_factory, EventModelFactory $event_model_factory, Acl $acl)
    {
        $this->event_factory = $event_factory;
        $this->event_model_factory = $event_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->event_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);
            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->event_model_factory);
        }

        $cursor = $this->event_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);
        return Helper::getAll($request, $identity, $this->acl, $cursor, $this->event_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $event): ResponseInterface
    {
        $resource = $this->event_factory->getOne($identity, $event);
        return Helper::getOne($request, $identity, $resource, $this->event_model_factory);
    }
}
