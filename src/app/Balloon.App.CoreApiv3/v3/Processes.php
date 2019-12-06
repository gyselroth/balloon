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
use Balloon\Process\Factory as ProcessFactory;
use Balloon\Rest\Helper;
use Balloon\User;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\App\CoreApiv3\v3\Models\ProcessFactory as ProcessModelFactory;

class Processes
{
    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $process_factory;

    /**
     * Init.
     */
    public function __construct(ProcessFactory $process_factory, ProcessModelFactory $process_model_factory, Acl $acl)
    {
        $this->process_factory = $process_factory;
        $this->process_model_factory = $process_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->process_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor);
        }

        $processs = $this->process_factory->getAll($identity, $query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $processs, $this->process_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $process): ResponseInterface
    {
        $resource = $this->process_factory->getOne($identity, $process);
        return Helper::getOne($request, $identity, $resource, $this->process_model_factory);
    }

    /**
     * Delete node.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $process): ResponseInterface
    {
        $this->process_factory->deleteOne($identity, $process);

        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }
}
