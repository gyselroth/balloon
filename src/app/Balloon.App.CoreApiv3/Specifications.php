<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api;

use Balloon\Resource\Factory as ResourceFactory;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;

class Specifications
{
    /**
     * Resource factory.
     *
     * @var ResourceFactory
     */
    protected $resource_factory;

    /**
     * Init.
     */
    public function __construct(ResourceFactory $resource_factory)
    {
        $this->resource_factory = $resource_factory;
    }

    /**
     * Get spec.
     */
    public function getApiv1(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_OK),
            $this->resource_factory->getSpecification(),
            ['pretty' => isset($query['pretty'])]
        );
    }
}
